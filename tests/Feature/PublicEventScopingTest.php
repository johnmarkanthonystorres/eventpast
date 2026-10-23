<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEventScopingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $a;
    private Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Tenant::create(['name' => 'Alpha', 'subdomain' => 'alpha']);
        $this->b = Tenant::create(['name' => 'Beta', 'subdomain' => 'beta']);
    }

    private function event(Tenant $tenant, string $title = 'Meetup', array $over = []): Event
    {
        $event = Event::create(array_merge([
            'tenant_id' => $tenant->id,
            'title' => $title,
            'start_at' => now()->addWeek(),
            'end_at' => now()->addWeek()->addHour(),
            'timezone' => 'UTC',
            'status' => 'published',
        ], $over));

        $event->ticketTypes()->create(['name' => 'GA', 'price' => 0]);

        return $event;
    }

    public function test_same_title_in_one_tenant_gets_a_unique_slug_instead_of_crashing(): void
    {
        $first = $this->event($this->a);
        $second = $this->event($this->a);
        $third = $this->event($this->a);

        $this->assertSame('meetup', $first->slug);
        $this->assertSame('meetup-2', $second->slug);
        $this->assertSame('meetup-3', $third->slug);
    }

    public function test_slug_of_a_deleted_event_is_not_reused(): void
    {
        $first = $this->event($this->a);
        $first->delete(); // soft delete still holds the unique key

        $this->assertSame('meetup-2', $this->event($this->a)->slug);
    }

    public function test_same_slug_in_different_tenants_is_allowed(): void
    {
        $this->assertSame('meetup', $this->event($this->a)->slug);
        $this->assertSame('meetup', $this->event($this->b)->slug);
    }

    public function test_each_tenants_url_shows_its_own_event(): void
    {
        $ea = $this->event($this->a);
        $eb = $this->event($this->b);

        $this->get(route('public.events.show', [$this->a, $ea]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('event.id', $ea->id)->where('tenant.subdomain', 'alpha'));

        $this->get(route('public.events.show', [$this->b, $eb]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('event.id', $eb->id)->where('tenant.subdomain', 'beta'));
    }

    public function test_an_event_is_not_reachable_under_another_tenants_url(): void
    {
        $ea = $this->event($this->a, 'Only Alpha');

        $this->get("/e/beta/{$ea->slug}")->assertNotFound();
    }

    public function test_registering_through_another_tenants_url_is_refused(): void
    {
        $ea = $this->event($this->a, 'Only Alpha');

        $this->post("/e/beta/{$ea->slug}/register", [
            'name' => 'Eve', 'email' => 'eve@example.com', 'ticket_type_id' => $ea->ticketTypes()->first()->id,
        ])->assertNotFound();

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_registration_lands_in_the_right_tenants_event(): void
    {
        $ea = $this->event($this->a);
        $eb = $this->event($this->b);

        $this->post(route('public.events.register', [$this->b, $eb]), [
            'name' => 'Bea', 'email' => 'bea@example.com', 'ticket_type_id' => $eb->ticketTypes()->first()->id,
        ])->assertRedirect();

        $this->assertSame($eb->id, Registration::firstOrFail()->event_id);
        $this->assertSame(0, $ea->registrations()->count());
    }

    public function test_share_url_helper_includes_the_organizer(): void
    {
        $ea = $this->event($this->a);

        $this->assertStringEndsWith('/e/alpha/meetup', $ea->getRegistrationUrl());
    }
}
