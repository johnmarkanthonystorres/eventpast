<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Registration;
use App\Models\ReminderLog;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Notifications\EventReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EventRemindersTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(now()->setTime(12, 0));

        $this->tenant = Tenant::create(['name' => 'Acme', 'subdomain' => 'acme']);
    }

    private function makeRegistration(string $startsIn, array $event = [], array $reg = []): Registration
    {
        static $n = 0;
        $n++;

        $e = Event::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'title' => "Event {$n}",
            'slug' => "event-{$n}",
            'start_at' => now()->modify($startsIn),
            'end_at' => now()->modify($startsIn)->addHours(3),
            'timezone' => 'UTC',
            'status' => 'published',
        ], $event));

        $ticket = TicketType::create(['event_id' => $e->id, 'name' => 'General']);
        $attendee = Attendee::create([
            'tenant_id' => $this->tenant->id,
            'name' => "Person {$n}",
            'email' => "person{$n}@example.com",
        ]);

        return Registration::create(array_merge([
            'event_id' => $e->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticket->id,
            'status' => 'confirmed',
        ], $reg));
    }

    public function test_sends_week_reminder_six_days_out(): void
    {
        $r = $this->makeRegistration('+6 days');

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentTo($r->attendee, EventReminder::class, fn ($n) => $n->type === '7d');
        $this->assertDatabaseHas('reminder_logs', ['registration_id' => $r->id, 'type' => '7d']);
    }

    public function test_sends_day_reminder_inside_24_hours_not_week(): void
    {
        $r = $this->makeRegistration('+20 hours');

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentToTimes($r->attendee, EventReminder::class, 1);
        Notification::assertSentTo($r->attendee, EventReminder::class, fn ($n) => $n->type === '1d');
    }

    public function test_sends_two_hour_reminder(): void
    {
        $r = $this->makeRegistration('+90 minutes');

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentTo($r->attendee, EventReminder::class, fn ($n) => $n->type === '2h');
    }

    public function test_does_not_send_twice_for_same_window(): void
    {
        $r = $this->makeRegistration('+6 days');

        $this->artisan('reminders:send');
        $this->artisan('reminders:send');

        Notification::assertSentToTimes($r->attendee, EventReminder::class, 1);
        $this->assertSame(1, ReminderLog::count());
    }

    public function test_sends_next_reminder_as_event_approaches(): void
    {
        $r = $this->makeRegistration('+6 days');
        $this->artisan('reminders:send');

        $this->travelTo(now()->addDays(5)->addHours(2)); // ~22h before start
        $this->artisan('reminders:send');

        Notification::assertSentToTimes($r->attendee, EventReminder::class, 2);
        $this->assertEqualsCanonicalizing(['7d', '1d'], ReminderLog::pluck('type')->all());
    }

    public function test_ignores_far_future_events(): void
    {
        $this->makeRegistration('+10 days');

        $this->artisan('reminders:send');

        Notification::assertNothingSent();
    }

    public function test_ignores_past_events(): void
    {
        $this->makeRegistration('-1 hour');

        $this->artisan('reminders:send');

        Notification::assertNothingSent();
    }

    public function test_skips_unpublished_and_cancelled_events(): void
    {
        $this->makeRegistration('+3 hours', ['status' => 'draft']);
        $this->makeRegistration('+3 hours', ['status' => 'cancelled']);

        $this->artisan('reminders:send');

        Notification::assertNothingSent();
    }

    public function test_skips_waitlisted_and_cancelled_registrations(): void
    {
        $this->makeRegistration('+3 hours', [], ['status' => 'waitlisted']);
        $this->makeRegistration('+3 hours', [], ['status' => 'cancelled']);

        $this->artisan('reminders:send');

        Notification::assertNothingSent();
    }

    public function test_respects_event_timezone(): void
    {
        // Stored wall-clock is 13:00 "Asia/Manila" (UTC+8) = 05:00 UTC, which
        // is already past when "now" is 12:00 UTC. Nothing should be sent,
        // even though 13:00 read naively as UTC would be an hour away.
        $this->makeRegistration('+1 hour', ['timezone' => 'Asia/Manila']);

        $this->artisan('reminders:send');

        Notification::assertNothingSent();
    }

    public function test_timezone_ahead_of_utc_still_reminds_when_upcoming(): void
    {
        // Wall-clock 22:00 in Manila = 14:00 UTC, i.e. 2h after "now".
        $r = $this->makeRegistration('+10 hours', ['timezone' => 'Asia/Manila']);

        $this->artisan('reminders:send');

        Notification::assertSentTo($r->attendee, EventReminder::class, fn ($n) => $n->type === '2h');
    }

    public function test_dry_run_sends_and_logs_nothing(): void
    {
        $this->makeRegistration('+3 hours');

        $this->artisan('reminders:send --dry-run')
            ->expectsOutputToContain('Would send 1')
            ->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertSame(0, ReminderLog::count());
    }
}
