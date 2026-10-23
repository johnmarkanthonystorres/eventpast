<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Referral;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\RegistrationConfirmed;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test';

    private Tenant $tenant;
    private Event $event;
    private TicketType $paid;
    private TicketType $free;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.secret' => 'sk_test_123',
            'services.stripe.webhook_secret' => self::SECRET,
            'services.stripe.connect_webhook_secret' => 'whsec_connect',
            'services.stripe.currency' => 'php',
        ]);

        Notification::fake();

        $this->tenant = Tenant::create([
            'name' => 'Acme', 'subdomain' => 'acme', 'plan' => 'free',
            'stripe_account_id' => 'acct_123', 'stripe_charges_enabled' => true,
        ]);

        $this->event = Event::create([
            'tenant_id' => $this->tenant->id, 'title' => 'Gala', 'slug' => 'gala',
            'start_at' => now()->addWeek(), 'end_at' => now()->addWeek()->addHours(3),
            'timezone' => 'UTC', 'status' => 'published', 'capacity' => 2,
        ]);

        $this->paid = $this->event->ticketTypes()->create(['name' => 'VIP', 'price' => 500]);
        $this->free = $this->event->ticketTypes()->create(['name' => 'GA', 'price' => 0]);
    }

    // ---- helpers -------------------------------------------------------

    private int $sessionCounter = 0;
    private bool $checkoutFaked = false;

    /** Every call to Stripe gets a fresh session id, like the real thing. */
    private function fakeCheckout(): void
    {
        if ($this->checkoutFaked) {
            return;
        }
        $this->checkoutFaked = true;

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => function () {
                $id = 'cs_test_'.++$this->sessionCounter;

                return Http::response(['id' => $id, 'url' => "https://checkout.stripe.com/c/pay/{$id}", 'payment_intent' => null]);
            },
        ]);
    }

    private function register(TicketType $ticket, string $email = 'buyer@example.com', array $extra = [])
    {
        return $this->post(route('public.events.register', [$this->tenant, $this->event]), array_merge([
            'name' => 'Buyer', 'email' => $email, 'ticket_type_id' => $ticket->id,
        ], $extra));
    }

    private function pendingRegistration(string $email = 'buyer@example.com'): Registration
    {
        $this->fakeCheckout();
        $this->register($this->paid, $email);

        return Registration::firstOrFail();
    }

    private function webhook(array $event, ?string $secret = self::SECRET, ?int $timestamp = null)
    {
        $payload = json_encode($event);
        $t = $timestamp ?? time();
        $sig = hash_hmac('sha256', "{$t}.{$payload}", $secret ?? '');

        return $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    private function sessionEvent(string $type, Order $order, array $over = []): array
    {
        return [
            'id' => 'evt_1',
            'type' => $type,
            'data' => ['object' => array_merge([
                'id' => $order->stripe_checkout_session_id,
                'payment_status' => 'paid',
                'amount_total' => PaymentService::toMinor($order->amount, $order->currency),
                'currency' => strtolower($order->currency),
                'payment_intent' => 'pi_123',
            ], $over)],
        ];
    }

    // ---- fees ----------------------------------------------------------

    public function test_platform_fee_follows_the_plan(): void
    {
        $svc = app(PaymentService::class);

        $this->assertSame(2550, $svc->platformFeeMinor($this->tenant, 50000, 'php')); // 5% + 0.50
        $this->tenant->plan = 'pro';
        $this->assertSame(1250, $svc->platformFeeMinor($this->tenant, 50000, 'php')); // 2.5%
        $this->tenant->plan = 'enterprise';
        $this->assertSame(0, $svc->platformFeeMinor($this->tenant, 50000, 'php'));
    }

    public function test_fee_never_exceeds_the_charge(): void
    {
        $this->assertSame(30, app(PaymentService::class)->platformFeeMinor($this->tenant, 30, 'php'));
    }

    public function test_zero_decimal_currencies_are_not_multiplied(): void
    {
        $this->assertSame(500, PaymentService::toMinor(500, 'jpy'));
        $this->assertSame(50000, PaymentService::toMinor(500, 'php'));
        $this->assertSame(500.0, PaymentService::fromMinor(50000, 'php'));
    }

    // ---- starting a payment -------------------------------------------

    public function test_paid_registration_holds_a_seat_and_goes_to_stripe(): void
    {
        $this->fakeCheckout();

        $this->register($this->paid)->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

        $reg = Registration::firstOrFail();
        $this->assertSame('pending_payment', $reg->status);
        $this->assertSame(1, $this->paid->fresh()->quantity_sold);
        $this->assertSame(1, $this->event->seatHoldingRegistrations()->count());

        $order = $reg->order;
        $this->assertSame('pending', $order->status);
        $this->assertEquals(500.00, $order->amount);
        $this->assertEquals(25.50, $order->platform_fee);
        $this->assertSame('PHP', $order->currency);
        $this->assertSame('cs_test_1', $order->stripe_checkout_session_id);

        Notification::assertNothingSent(); // no "confirmed" email before payment
    }

    public function test_checkout_session_pays_the_organizer_and_takes_the_fee(): void
    {
        $this->fakeCheckout();
        $this->register($this->paid);

        Http::assertSent(function (HttpRequest $r) {
            if (! str_ends_with($r->url(), '/checkout/sessions')) {
                return false;
            }
            $d = $r->data();

            return $r->hasHeader('Authorization', 'Bearer sk_test_123')
                && $r->hasHeader('Idempotency-Key')
                && $d['mode'] === 'payment'
                && $d['line_items'][0]['price_data']['currency'] === 'php'
                && $d['line_items'][0]['price_data']['unit_amount'] === 50000
                && $d['payment_intent_data']['application_fee_amount'] === 2550
                && $d['payment_intent_data']['transfer_data']['destination'] === 'acct_123'
                && $d['customer_email'] === 'buyer@example.com'
                && $d['expires_at'] > time() + 25 * 60
                && str_contains($d['success_url'], '/registrations/'.Registration::first()->qr_token.'/confirmation');
        });
    }

    public function test_stripe_failure_releases_the_seat(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $this->register($this->paid)->assertSessionHas('error');

        $reg = Registration::firstOrFail();
        $this->assertSame('cancelled', $reg->status);
        $this->assertSame('failed', $reg->order->status);
        $this->assertSame(0, $this->paid->fresh()->quantity_sold);
        $this->assertSame(0, $this->event->seatHoldingRegistrations()->count());
    }

    public function test_paid_tickets_refused_when_organizer_cannot_take_payments(): void
    {
        $this->tenant->update(['stripe_charges_enabled' => false]);

        $this->register($this->paid)->assertSessionHas('error');

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_free_tickets_still_confirm_immediately(): void
    {
        $this->register($this->free)->assertRedirect();

        $this->assertSame('confirmed', Registration::firstOrFail()->status);
        $this->assertDatabaseCount('orders', 0);
        Notification::assertSentTo(Attendee::firstOrFail(), RegistrationConfirmed::class);
    }

    public function test_pending_payment_counts_against_capacity(): void
    {
        $this->fakeCheckout();
        $this->register($this->paid, 'a@example.com');
        $this->register($this->free, 'b@example.com');

        $this->assertTrue($this->event->fresh()->is_full);

        // A third buyer is waitlisted, with no order and no Stripe session.
        $this->register($this->paid, 'c@example.com');
        $third = Registration::whereHas('attendee', fn ($q) => $q->where('email', 'c@example.com'))->firstOrFail();
        $this->assertSame('waitlisted', $third->status);
        $this->assertNull($third->order_id);
    }

    public function test_paid_waitlisted_registration_cannot_be_promoted_for_free(): void
    {
        $a = Attendee::create(['tenant_id' => $this->tenant->id, 'name' => 'W', 'email' => 'w@example.com']);
        $reg = Registration::create([
            'event_id' => $this->event->id, 'attendee_id' => $a->id,
            'ticket_type_id' => $this->paid->id, 'status' => 'waitlisted',
        ]);

        $this->assertFalse($reg->promoteFromWaitlist());
        $this->assertSame('waitlisted', $reg->fresh()->status);
    }

    // ---- webhooks ------------------------------------------------------

    public function test_webhook_with_bad_signature_is_rejected(): void
    {
        $reg = $this->pendingRegistration();

        $this->webhook($this->sessionEvent('checkout.session.completed', $reg->order), 'wrong_secret')->assertStatus(400);

        $this->assertSame('pending_payment', $reg->fresh()->status);
    }

    public function test_webhook_with_stale_timestamp_is_rejected(): void
    {
        $reg = $this->pendingRegistration();

        $this->webhook($this->sessionEvent('checkout.session.completed', $reg->order), self::SECRET, time() - 3600)
            ->assertStatus(400);

        $this->assertSame('pending_payment', $reg->fresh()->status);
    }

    public function test_successful_payment_confirms_the_registration(): void
    {
        $reg = $this->pendingRegistration();

        $this->webhook($this->sessionEvent('checkout.session.completed', $reg->order))->assertOk();

        $this->assertSame('confirmed', $reg->fresh()->status);
        $this->assertSame('completed', $reg->order->fresh()->status);
        $this->assertSame('pi_123', $reg->order->fresh()->stripe_payment_intent_id);
        $this->assertDatabaseHas('payments', ['order_id' => $reg->order_id, 'status' => 'succeeded', 'amount' => 500]);
        Notification::assertSentTo($reg->attendee, RegistrationConfirmed::class);
    }

    public function test_replayed_webhook_does_not_double_confirm(): void
    {
        $reg = $this->pendingRegistration();
        $event = $this->sessionEvent('checkout.session.completed', $reg->order);

        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk();

        $this->assertDatabaseCount('payments', 1);
        Notification::assertSentToTimes($reg->attendee, RegistrationConfirmed::class, 1);
    }

    public function test_unpaid_completed_session_waits_for_async_payment(): void
    {
        $reg = $this->pendingRegistration();

        $this->webhook($this->sessionEvent('checkout.session.completed', $reg->order, ['payment_status' => 'unpaid']))->assertOk();
        $this->assertSame('pending_payment', $reg->fresh()->status);

        $this->webhook($this->sessionEvent('checkout.session.async_payment_succeeded', $reg->order))->assertOk();
        $this->assertSame('confirmed', $reg->fresh()->status);
    }

    public function test_amount_mismatch_is_not_confirmed(): void
    {
        $reg = $this->pendingRegistration();

        $this->webhook($this->sessionEvent('checkout.session.completed', $reg->order, ['amount_total' => 100]))->assertOk();

        $this->assertSame('pending_payment', $reg->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_currency_mismatch_is_not_confirmed(): void
    {
        $reg = $this->pendingRegistration();

        $this->webhook($this->sessionEvent('checkout.session.completed', $reg->order, ['currency' => 'usd']))->assertOk();

        $this->assertSame('pending_payment', $reg->fresh()->status);
    }

    public function test_expired_session_releases_the_seat(): void
    {
        $reg = $this->pendingRegistration();

        $this->webhook($this->sessionEvent('checkout.session.expired', $reg->order, ['payment_status' => 'unpaid']))->assertOk();

        $this->assertSame('cancelled', $reg->fresh()->status);
        $this->assertSame('failed', $reg->order->fresh()->status);
        $this->assertSame(0, $this->paid->fresh()->quantity_sold);
        $this->assertSame(0, $this->event->seatHoldingRegistrations()->count());
    }

    public function test_expiry_after_payment_does_not_cancel_a_paid_registration(): void
    {
        $reg = $this->pendingRegistration();
        $this->webhook($this->sessionEvent('checkout.session.completed', $reg->order));

        $this->webhook($this->sessionEvent('checkout.session.expired', $reg->order))->assertOk();

        $this->assertSame('confirmed', $reg->fresh()->status);
        $this->assertSame(1, $this->paid->fresh()->quantity_sold);
    }

    public function test_unknown_session_is_acknowledged_and_ignored(): void
    {
        $this->webhook([
            'id' => 'evt_x', 'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_nope', 'payment_status' => 'paid']],
        ])->assertOk();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unrelated_events_are_acknowledged(): void
    {
        $this->webhook(['id' => 'evt_y', 'type' => 'customer.created', 'data' => ['object' => []]])->assertOk();
    }

    public function test_account_updated_toggles_charges_enabled(): void
    {
        $this->webhook(['id' => 'e', 'type' => 'account.updated', 'data' => ['object' => ['id' => 'acct_123', 'charges_enabled' => false]]], 'whsec_connect')->assertOk();
        $this->assertFalse($this->tenant->fresh()->canAcceptPayments());

        $this->webhook(['id' => 'e', 'type' => 'account.updated', 'data' => ['object' => ['id' => 'acct_123', 'charges_enabled' => true]]], 'whsec_connect')->assertOk();
        $this->assertTrue($this->tenant->fresh()->canAcceptPayments());
    }

    public function test_referral_conversion_counts_only_after_payment(): void
    {
        $giver = Attendee::create(['tenant_id' => $this->tenant->id, 'name' => 'G', 'email' => 'g@example.com']);
        $giverReg = Registration::create([
            'event_id' => $this->event->id, 'attendee_id' => $giver->id,
            'ticket_type_id' => $this->free->id, 'status' => 'cancelled',
        ]);
        $referral = Referral::create(['registration_id' => $giverReg->id]);

        $this->fakeCheckout();
        $this->register($this->paid, 'buyer@example.com', ['referral_code' => $referral->code]);
        $this->assertSame(0, (int) $referral->fresh()->conversions);

        $order = Order::firstOrFail();
        $this->webhook($this->sessionEvent('checkout.session.completed', $order))->assertOk();

        $this->assertSame(1, (int) $referral->fresh()->conversions);
    }

    // ---- stale sweep ---------------------------------------------------

    public function test_stale_unpaid_orders_are_released_but_fresh_ones_are_kept(): void
    {
        $stale = $this->pendingRegistration('old@example.com');
        Order::whereKey($stale->order_id)->update(['created_at' => now()->subHours(2)]);

        $this->fakeCheckout();
        $this->event->update(['capacity' => 10]);
        $this->register($this->paid, 'new@example.com');

        $this->artisan('payments:release-stale')->assertSuccessful();

        $this->assertSame('cancelled', $stale->fresh()->status);
        $this->assertSame(1, $this->event->seatHoldingRegistrations()->count());
    }

    // ---- confirmation URLs ---------------------------------------------

    public function test_confirmation_page_cannot_be_reached_by_guessing_ids(): void
    {
        $reg = $this->pendingRegistration();

        $this->get('/registrations/'.$reg->id.'/confirmation')->assertNotFound();
        $this->get('/registrations/'.$reg->id.'/qrcode')->assertNotFound();
        $this->get(route('registrations.confirmation', $reg))->assertOk();
    }

    public function test_pending_ticket_shows_waiting_state_not_a_qr_code(): void
    {
        $reg = $this->pendingRegistration();

        $this->get(route('registrations.confirmation', $reg))
            ->assertInertia(fn ($p) => $p->component('Registrations/Confirmation')->where('registration.status', 'pending_payment'));
    }

    // ---- Connect onboarding --------------------------------------------

    public function test_admin_can_start_onboarding_and_account_is_created_once(): void
    {
        $this->tenant->update(['stripe_account_id' => null, 'stripe_charges_enabled' => false]);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);

        Http::fake([
            'api.stripe.com/v1/accounts' => Http::response(['id' => 'acct_new']),
            'api.stripe.com/v1/account_links' => Http::response(['url' => 'https://connect.stripe.com/setup/x']),
        ]);

        $this->actingAs($admin)->post(route('settings.payments.connect'))->assertRedirect('https://connect.stripe.com/setup/x');
        $this->assertSame('acct_new', $this->tenant->fresh()->stripe_account_id);

        $this->actingAs($admin)->post(route('settings.payments.connect'));
        Http::assertSentCount(3); // 1 account create + 2 links, not 2 accounts
    }

    public function test_return_from_stripe_enables_payments_when_account_is_ready(): void
    {
        $this->tenant->update(['stripe_charges_enabled' => false]);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);

        Http::fake(['api.stripe.com/v1/accounts/acct_123' => Http::response(['id' => 'acct_123', 'charges_enabled' => true])]);

        $this->actingAs($admin)->get(route('settings.payments.return'))->assertRedirect(route('settings.payments'));

        $this->assertTrue($this->tenant->fresh()->canAcceptPayments());
    }

    public function test_staff_cannot_open_payment_settings(): void
    {
        $staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);

        $this->actingAs($staff)->get(route('settings.payments'))->assertForbidden();
        $this->actingAs($staff)->post(route('settings.payments.connect'))->assertForbidden();
    }

    public function test_stripe_account_id_is_never_sent_to_the_browser(): void
    {
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);

        $this->actingAs($admin)->get(route('settings.payments'))
            ->assertOk()
            ->assertDontSee('acct_123');

        $this->get(route('public.events.show', [$this->tenant, $this->event]))->assertDontSee('acct_123');
    }
}
