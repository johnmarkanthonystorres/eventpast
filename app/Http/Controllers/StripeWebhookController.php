<?php

namespace App\Http\Controllers;

use App\Exceptions\StripeException;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\PaymentService;
use App\Services\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        private StripeGateway $stripe,
        private PaymentService $payments,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            // Checkout events arrive on the platform endpoint; account.updated
            // comes from a Connect endpoint with its own signing secret.
            $event = $this->stripe->verifyWebhook(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                [
                    config('services.stripe.webhook_secret'),
                    config('services.stripe.connect_webhook_secret'),
                ],
            );
        } catch (StripeException $e) {
            Log::warning('Stripe webhook rejected: '.$e->getMessage());

            return response('Invalid signature', 400);
        }

        $object = $event['data']['object'] ?? [];

        match ($event['type']) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => $this->payments->fulfillCheckout($object),

            'checkout.session.expired',
            'checkout.session.async_payment_failed' => $this->releaseSession($object),

            'account.updated' => $this->syncAccount($object),

            default => null, // acknowledge events we don't act on
        };

        return response('ok', 200);
    }

    /** @param array<string, mixed> $session */
    private function releaseSession(array $session): void
    {
        $order = Order::where('stripe_checkout_session_id', $session['id'] ?? '')->first();

        if ($order) {
            $this->payments->release($order);
        }
    }

    /** @param array<string, mixed> $account */
    private function syncAccount(array $account): void
    {
        $tenant = Tenant::where('stripe_account_id', $account['id'] ?? '')->first();

        $tenant?->update(['stripe_charges_enabled' => (bool) ($account['charges_enabled'] ?? false)]);
    }
}
