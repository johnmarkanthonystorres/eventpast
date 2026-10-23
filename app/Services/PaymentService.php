<?php

namespace App\Services;

use App\Exceptions\StripeException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Notifications\RegistrationConfirmed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /** Currencies Stripe treats as having no minor unit. */
    private const ZERO_DECIMAL = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

    public function __construct(private StripeGateway $stripe)
    {
    }

    // ---- Money ---------------------------------------------------------

    public static function toMinor(float|int|string $amount, string $currency): int
    {
        $factor = in_array(strtolower($currency), self::ZERO_DECIMAL, true) ? 1 : 100;

        return (int) round(((float) $amount) * $factor);
    }

    public static function fromMinor(int $minor, string $currency): float
    {
        $factor = in_array(strtolower($currency), self::ZERO_DECIMAL, true) ? 1 : 100;

        return round($minor / $factor, 2);
    }

    /**
     * Platform fee in minor units: the plan's percentage plus its fixed fee,
     * never more than the charge itself.
     */
    public function platformFeeMinor(Tenant $tenant, int $amountMinor, string $currency): int
    {
        $percent = (int) round($amountMinor * ((float) $tenant->ticket_fee_percentage) / 100);
        $fixed = self::toMinor($tenant->fixed_ticket_fee, $currency);

        return min($amountMinor, $percent + $fixed);
    }

    // ---- Starting a payment -------------------------------------------

    /**
     * Create the pending order for a registration. Call inside the same
     * transaction that creates the registration.
     */
    public function createOrder(Registration $registration, TicketType $ticket, Tenant $tenant): Order
    {
        $currency = config('services.stripe.currency');
        $amountMinor = self::toMinor($ticket->price, $currency);
        $feeMinor = $this->platformFeeMinor($tenant, $amountMinor, $currency);

        $order = Order::create([
            'event_id' => $registration->event_id,
            'attendee_id' => $registration->attendee_id,
            'amount' => self::fromMinor($amountMinor, $currency),
            'platform_fee' => self::fromMinor($feeMinor, $currency),
            'currency' => strtoupper($currency),
            'status' => 'pending',
        ]);

        $registration->update(['order_id' => $order->id]);

        return $order;
    }

    /**
     * Create the hosted Stripe Checkout session and return its URL.
     * The buyer pays the organizer directly; the platform fee is routed to us.
     *
     * @throws StripeException
     */
    public function createCheckout(Registration $registration): string
    {
        $registration->loadMissing(['event.tenant', 'attendee', 'ticketType', 'order']);

        $order = $registration->order;
        $tenant = $registration->event->tenant;
        $currency = strtolower($order->currency);

        $session = $this->stripe->createCheckoutSession([
            'mode' => 'payment',
            'client_reference_id' => (string) $order->id,
            'customer_email' => $registration->attendee->email,
            'expires_at' => now()->addMinutes(config('services.stripe.checkout_minutes'))->timestamp,
            'success_url' => route('registrations.confirmation', $registration).'?paid=1',
            'cancel_url' => route('public.events.show', $registration->event),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => self::toMinor($order->amount, $currency),
                    'product_data' => [
                        'name' => $registration->event->title.' – '.$registration->ticketType->name,
                    ],
                ],
            ]],
            'payment_intent_data' => [
                'application_fee_amount' => self::toMinor($order->platform_fee, $currency),
                'transfer_data' => ['destination' => $tenant->stripe_account_id],
            ],
            'metadata' => [
                'order_id' => (string) $order->id,
                'registration_id' => (string) $registration->id,
                'tenant_id' => (string) $tenant->id,
            ],
        ], "checkout-order-{$order->id}");

        $order->update([
            'stripe_checkout_session_id' => $session['id'],
            'stripe_payment_intent_id' => is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null,
        ]);

        return $session['url'];
    }

    // ---- Webhook outcomes ---------------------------------------------

    /**
     * Confirm a registration once Stripe reports the session as paid.
     * Safe to call repeatedly: Stripe retries webhooks.
     *
     * @param  array<string, mixed>  $session
     * @return string  confirmed | duplicate | unpaid | unknown | mismatch | not_pending
     */
    public function fulfillCheckout(array $session): string
    {
        $registration = null;

        $outcome = DB::transaction(function () use ($session, &$registration) {
            $order = Order::where('stripe_checkout_session_id', $session['id'] ?? '')->lockForUpdate()->first();

            if (! $order) {
                return 'unknown';
            }

            if ($order->status === 'completed') {
                return 'duplicate';
            }

            if (($session['payment_status'] ?? null) !== 'paid') {
                return 'unpaid'; // async method still settling; a later event finishes it
            }

            $currency = strtolower($order->currency);
            $expected = self::toMinor($order->amount, $currency);

            if ((int) ($session['amount_total'] ?? -1) !== $expected
                || strtolower((string) ($session['currency'] ?? '')) !== $currency) {
                Log::error('Stripe session does not match order; not confirming.', [
                    'order_id' => $order->id,
                    'expected' => $expected,
                    'amount_total' => $session['amount_total'] ?? null,
                    'currency' => $session['currency'] ?? null,
                ]);

                return 'mismatch';
            }

            $registration = Registration::where('order_id', $order->id)->lockForUpdate()->first();

            if (! $registration || $registration->status !== 'pending_payment') {
                // Money arrived for a seat we no longer hold. Needs a human (refund).
                Log::critical('Payment received for a registration that is not pending.', [
                    'order_id' => $order->id,
                    'registration_status' => $registration?->status,
                ]);

                $order->update(['status' => 'completed']);

                return 'not_pending';
            }

            $order->update([
                'status' => 'completed',
                'stripe_payment_intent_id' => is_string($session['payment_intent'] ?? null)
                    ? $session['payment_intent']
                    : $order->stripe_payment_intent_id,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->amount,
                'payment_method' => 'stripe_checkout',
                'status' => 'succeeded',
                'paid_at' => now(),
            ]);

            $registration->update(['status' => 'confirmed']);

            return 'confirmed';
        });

        if ($outcome === 'confirmed' && $registration) {
            $this->countReferralConversion($registration);
            $registration->attendee->notify(new RegistrationConfirmed($registration));
        }

        return $outcome;
    }

    /**
     * Give the seat and ticket back when a payment will never arrive
     * (session expired, async payment failed, or Stripe was unreachable).
     */
    public function release(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $order || $order->status !== 'pending') {
                return false;
            }

            $order->update(['status' => 'failed']);

            $registration = Registration::where('order_id', $order->id)->lockForUpdate()->first();

            if ($registration && $registration->status === 'pending_payment') {
                $registration->update(['status' => 'cancelled']);

                TicketType::where('id', $registration->ticket_type_id)
                    ->where('quantity_sold', '>', 0)
                    ->decrement('quantity_sold');
            }

            return true;
        });
    }

    private function countReferralConversion(Registration $registration): void
    {
        if (! str_starts_with((string) $registration->source, 'referral:')) {
            return;
        }

        Referral::where('code', substr($registration->source, strlen('referral:')))
            ->first()
            ?->incrementConversions();
    }
}
