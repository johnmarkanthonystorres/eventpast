<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketTypeController extends Controller
{
    public function index(Event $event): Response
    {
        $this->authorize('view', $event);

        return Inertia::render('Events/Tickets', [
            'event' => $event,
            'tickets' => $event->ticketTypes()->orderBy('id')->get(),
            'canManage' => request()->user()->can('update', $event),
            'paymentsReady' => $event->tenant->canAcceptPayments(),
            'currency' => config('services.stripe.currency'),
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $this->validated($request, $event);

        $event->ticketTypes()->create($validated);

        return back()->with('success', 'Ticket added');
    }

    public function update(Request $request, Event $event, TicketType $ticketType): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($ticketType->event_id === $event->id, 404);

        $validated = $this->validated($request, $event, $ticketType);

        $ticketType->update($validated);

        return back()->with('success', 'Ticket updated');
    }

    public function destroy(Event $event, TicketType $ticketType): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($ticketType->event_id === $event->id, 404);

        // Registrations cascade-delete with their ticket type, so a ticket
        // that has ever been claimed can only be switched off, never removed.
        if ($ticketType->quantity_sold > 0 || $ticketType->registrations()->withTrashed()->exists()) {
            return back()->with('error', 'This ticket already has registrations. Deactivate it instead of deleting it.');
        }

        $ticketType->delete();

        return back()->with('success', 'Ticket deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Event $event, ?TicketType $existing = null): array
    {
        $minQuantity = max(1, (int) ($existing?->quantity_sold ?? 0));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'quantity' => ['nullable', 'integer', "min:{$minQuantity}"],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after:sales_start_at'],
            'is_active' => ['boolean'],
        ], [
            'quantity.min' => $existing && $existing->quantity_sold > 0
                ? "Quantity can't be lower than the {$existing->quantity_sold} already sold."
                : 'Quantity must be at least 1 (leave blank for unlimited).',
        ]);

        $data['price'] = round((float) $data['price'], 2);

        // Paid tickets need a payout account that can actually take payments,
        // otherwise a buyer could be sent to a checkout that cannot work.
        if ($data['price'] > 0 && ! $event->tenant->canAcceptPayments()) {
            abort(422, 'Connect a Stripe account in Payment settings before selling paid tickets.');
        }

        return $data;
    }
}
