<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $staff;
    private Event $event;
    private TicketType $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme', 'subdomain' => 'acme']);
        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);

        $this->event = Event::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Launch Party',
            'slug' => 'launch-party',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'timezone' => 'UTC',
            'status' => 'published',
        ]);

        $this->ticket = TicketType::create(['event_id' => $this->event->id, 'name' => 'General']);
    }

    private function register(string $name, string $status = 'confirmed', ?Event $event = null): Registration
    {
        $attendee = Attendee::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'email' => strtolower($name) . '@example.com',
        ]);

        return Registration::create([
            'event_id' => ($event ?? $this->event)->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $this->ticket->id,
            'status' => $status,
        ]);
    }

    public function test_valid_scan_checks_in_and_records_scanner(): void
    {
        $r = $this->register('Ana');

        $this->actingAs($this->staff)
            ->postJson(route('checkin.scan'), ['qr_token' => $r->qr_token])
            ->assertOk()
            ->assertJson(['success' => true, 'result' => 'valid']);

        $this->assertDatabaseHas('check_ins', ['registration_id' => $r->id, 'scanned_by' => $this->staff->id]);
        $this->assertSame('checked_in', $r->fresh()->status);
    }

    public function test_door_staff_who_are_not_admins_can_scan(): void
    {
        $this->assertSame('staff', $this->staff->role);
        $r = $this->register('Ana');

        $this->actingAs($this->staff)
            ->postJson(route('checkin.scan'), ['qr_token' => $r->qr_token])
            ->assertOk()
            ->assertJson(['result' => 'valid']);
    }

    public function test_second_scan_is_reported_as_duplicate(): void
    {
        $r = $this->register('Ana');

        $this->actingAs($this->staff)->postJson(route('checkin.scan'), ['qr_token' => $r->qr_token]);

        $this->actingAs($this->staff)
            ->postJson(route('checkin.scan'), ['qr_token' => $r->qr_token])
            ->assertJson(['success' => false, 'result' => 'duplicate']);

        $this->assertDatabaseCount('check_ins', 1);
    }

    public function test_unknown_code_is_invalid(): void
    {
        $this->actingAs($this->staff)
            ->postJson(route('checkin.scan'), ['qr_token' => 'nope'])
            ->assertStatus(404)
            ->assertJson(['result' => 'invalid']);
    }

    public function test_waitlisted_and_cancelled_are_rejected(): void
    {
        $w = $this->register('Wendy', 'waitlisted');
        $c = $this->register('Carl', 'cancelled');

        $this->actingAs($this->staff)
            ->postJson(route('checkin.scan'), ['qr_token' => $w->qr_token])
            ->assertJson(['success' => false, 'result' => 'waitlisted']);

        $this->actingAs($this->staff)
            ->postJson(route('checkin.scan'), ['qr_token' => $c->qr_token])
            ->assertJson(['success' => false, 'result' => 'cancelled']);

        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_cannot_scan_another_tenants_ticket(): void
    {
        $other = Tenant::create(['name' => 'Other', 'subdomain' => 'other']);
        $outsider = User::factory()->create(['tenant_id' => $other->id, 'role' => 'owner']);
        $r = $this->register('Ana');

        $this->actingAs($outsider)
            ->postJson(route('checkin.scan'), ['qr_token' => $r->qr_token])
            ->assertForbidden()
            ->assertJson(['result' => 'unauthorized']);

        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_guests_cannot_scan(): void
    {
        $r = $this->register('Ana');

        $this->postJson(route('checkin.scan'), ['qr_token' => $r->qr_token])->assertUnauthorized();
    }

    public function test_door_list_keeps_checked_in_people_and_counts_correctly(): void
    {
        $a = $this->register('Ana');
        $this->register('Ben');
        $this->register('Wendy', 'waitlisted');

        $this->actingAs($this->staff)->postJson(route('checkin.scan'), ['qr_token' => $a->qr_token]);

        $this->actingAs($this->staff)
            ->get(route('events.checkin', $this->event->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/CheckIn')
                ->where('stats.total', 2)
                ->where('stats.checked_in', 1)
                ->has('registrations.data', 2));
    }

    public function test_door_list_search_filters_by_name_or_email(): void
    {
        $this->register('Ana');
        $this->register('Ben');

        $this->actingAs($this->staff)
            ->get(route('events.checkin', ['event' => $this->event->id, 'search' => 'ben@']))
            ->assertInertia(fn ($page) => $page
                ->has('registrations.data', 1)
                ->where('registrations.data.0.attendee.name', 'Ben'));
    }

    public function test_door_list_is_tenant_scoped(): void
    {
        $other = Tenant::create(['name' => 'Other', 'subdomain' => 'other']);
        $outsider = User::factory()->create(['tenant_id' => $other->id]);

        $this->actingAs($outsider)
            ->get(route('events.checkin', $this->event->id))
            ->assertForbidden();
    }
}
