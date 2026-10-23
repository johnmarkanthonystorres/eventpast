<?php

namespace App\Http\Controllers;

use App\Exceptions\StripeException;
use App\Services\StripeGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentSettingsController extends Controller
{
    public function __construct(private StripeGateway $stripe)
    {
    }

    public function show(Request $request): Response
    {
        $tenant = $this->tenantFor($request);

        return Inertia::render('Settings/Payments', [
            'stripeConfigured' => filled(config('services.stripe.secret')),
            'hasAccount' => filled($tenant->stripe_account_id),
            'chargesEnabled' => (bool) $tenant->stripe_charges_enabled,
            'feePercentage' => $tenant->ticket_fee_percentage,
            'fixedFee' => $tenant->fixed_ticket_fee,
            'currency' => config('services.stripe.currency'),
        ]);
    }

    /**
     * Start (or resume) Stripe onboarding. Also used as the "link expired"
     * refresh target, hence reachable by GET.
     */
    public function connect(Request $request)
    {
        $tenant = $this->tenantFor($request);

        try {
            if (! $tenant->stripe_account_id) {
                $account = $this->stripe->createConnectedAccount(
                    $request->user()->email,
                    ['tenant_id' => (string) $tenant->id],
                );

                $tenant->update(['stripe_account_id' => $account['id']]);
            }

            $link = $this->stripe->createOnboardingLink(
                $tenant->stripe_account_id,
                route('settings.payments.return'),
                route('settings.payments.connect'),
            );
        } catch (StripeException $e) {
            return redirect()->route('settings.payments')->with('error', $e->getMessage());
        }

        return Inertia::location($link['url']);
    }

    /** Where Stripe sends the organizer back to; re-read the account state. */
    public function return(Request $request): RedirectResponse
    {
        $tenant = $this->tenantFor($request);

        if (! $tenant->stripe_account_id) {
            return redirect()->route('settings.payments');
        }

        try {
            $account = $this->stripe->retrieveAccount($tenant->stripe_account_id);
        } catch (StripeException $e) {
            return redirect()->route('settings.payments')->with('error', $e->getMessage());
        }

        $enabled = (bool) ($account['charges_enabled'] ?? false);
        $tenant->update(['stripe_charges_enabled' => $enabled]);

        return redirect()->route('settings.payments')->with(
            $enabled ? 'success' : 'error',
            $enabled
                ? 'Stripe is connected. You can now sell paid tickets.'
                : 'Stripe still needs a few details before you can take payments. Continue setup to finish.'
        );
    }

    private function tenantFor(Request $request)
    {
        abort_unless($request->user()->isAdmin() && $request->user()->tenant_id, 403);

        return $request->user()->tenant;
    }
}
