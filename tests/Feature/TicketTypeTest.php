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

class TicketTypeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme', 'subdomain' => 'acme']);
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->event = Event::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Launch',
            'slug' => 'launch',
            'start_at' => now()->addWeek(),
            'end_at' => now()->addWeek()->addHours(2),
            'timezone' => 'UTC',
            'status' => 'draft',
        ]);
    }

    private function payload(array $over = []): array
    {
        return array_merge(['name' => 'VIP', 'price' => 0, 'quantity' => null, 'is_active' => true], $over);
    }

    private function connectStripe(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_test', 'stripe_charges_enabled' => true]);
    }

    public function test_new_events_get_a_default_free_ticket(): void
    {
        $this->actingAs($this->admin)->post(route('events.store'), [
            'title' => 'Fresh Event',
            'start_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'end_at' => now()->addWeek()->addHours(2)->format('Y-m-d\TH:i'),
            'timezone' => 'UTC',
        ])->assertRedirect();

        $event = Event::where('title', 'Fresh Event')->firstOrFail();

        $this->assertSame(1, $event->ticketTypes()->count());
        $ticket = $event->ticketTypes()->first();
        $this->assertSame('General Admission', $ticket->name);
        $this->assertTrue($ticket->is_free);
        $this->assertTrue($ticket->isAvailableForSale());
    }

    public function test_admin_can_add_a_free_ticket(): void
    {
        $this->actingAs($this->admin)
            ->post(route('events.tickets.store', $this->event->id), $this->payload(['quantity' => 50]))
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_types', ['event_id' => $this->event->id, 'name' => 'VIP', 'quantity' => 50]);
    }

    public function test_paid_ticket_blocked_until_stripe_is_ready(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('events.tickets.store', $this->event->id), $this->payload(['price' => 500]))
            ->assertStatus(422);

        $this->assertDatabaseCount('ticket_types', 0);

        // connected but not yet enabled by Stripe is still not enough
        $this->tenant->update(['stripe_account_id' => 'acct_test', 'stripe_charges_enabled' => false]);
        $this->actingAs($this->admin)
            ->postJson(route('events.tickets.store', $this->event->id), $this->payload(['price' => 500]))
            ->assertStatus(422);

        $this->connectStripe();
        $this->actingAs($this->admin)
            ->post(route('events.tickets.store', $this->event->id), $this->payload(['price' => 500]))
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_types', ['name' => 'VIP', 'price' => 500]);
    }

    public function test_quantity_cannot_drop_below_tickets_already_sold(): void
    {
        $t = $this->event->ticketTypes()->create(['name' => 'GA', 'price' => 0, 'quantity' => 100, 'quantity_sold' => 40]);

        $this->actingAs($this->admin)
            ->putJson(route('events.tickets.update', [$this->event->id, $t->id]), $this->payload(['name' => 'GA', 'quantity' => 10]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');

        $this->actingAs($this->admin)
            ->put(route('events.tickets.update', [$this->event->id, $t->id]), $this->payload(['name' => 'GA', 'quantity' => 40]))
            ->assertRedirect();

        $this->assertSame(40, $t->fresh()->quantity);
    }

    public function test_sales_end_must_be_after_start(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('events.tickets.store', $this->event->id), $this->payload([
                'sales_start_at' => now()->addDays(2)->toDateTimeString(),
                'sales_end_at' => now()->addDay()->toDateTimeString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sales_end_at');
    }

    public function test_ticket_with_registrations_cannot_be_deleted(): void
    {
        $t = $this->event->ticketTypes()->create(['name' => 'GA', 'price' => 0]);
        $a = Attendee::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@example.com']);
        Registration::create(['event_id' => $this->event->id, 'attendee_id' => $a->id, 'ticket_type_id' => $t->id]);

        $this->actingAs($this->admin)
            ->delete(route('events.tickets.destroy', [$this->event->id, $t->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('ticket_types', ['id' => $t->id]);
    }

    public function test_unused_ticket_can_be_deleted(): void
    {
        $t = $this->event->ticketTypes()->create(['name' => 'Spare', 'price' => 0]);

        $this->actingAs($this->admin)
            ->delete(route('events.tickets.destroy', [$this->event->id, $t->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ticket_types', ['id' => $t->id]);
    }

    public function test_staff_can_view_but_not_change_tickets(): void
    {
        $staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $t = $this->event->ticketTypes()->create(['name' => 'GA', 'price' => 0]);

        $this->actingAs($staff)->get(route('events.tickets.index', $this->event->id))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Events/Tickets')->where('canManage', false));

        $this->actingAs($staff)->post(route('events.tickets.store', $this->event->id), $this->payload())->assertForbidden();
        $this->actingAs($staff)->put(route('events.tickets.update', [$this->event->id, $t->id]), $this->payload())->assertForbidden();
        $this->actingAs($staff)->delete(route('events.tickets.destroy', [$this->event->id, $t->id]))->assertForbidden();
    }

    public function test_other_tenants_cannot_touch_tickets(): void
    {
        $other = Tenant::create(['name' => 'Other', 'subdomain' => 'other']);
        $outsider = User::factory()->create(['tenant_id' => $other->id, 'role' => 'owner']);

        $this->actingAs($outsider)->get(route('events.tickets.index', $this->event->id))->assertForbidden();
        $this->actingAs($outsider)->post(route('events.tickets.store', $this->event->id), $this->payload())->assertForbidden();
    }

    public function test_ticket_must_belong_to_the_event_in_the_url(): void
    {
        $otherEvent = Event::create([
            'tenant_id' => $this->tenant->id, 'title' => 'Other', 'slug' => 'other',
            'start_at' => now()->addWeek(), 'end_at' => now()->addWeek()->addHour(), 'timezone' => 'UTC', 'status' => 'draft',
        ]);
        $t = $otherEvent->ticketTypes()->create(['name' => 'GA', 'price' => 0]);

        $this->actingAs($this->admin)
            ->put(route('events.tickets.update', [$this->event->id, $t->id]), $this->payload())
            ->assertNotFound();
    }
}
