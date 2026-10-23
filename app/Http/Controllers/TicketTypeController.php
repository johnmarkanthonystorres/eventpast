<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\Request;

class TicketTypeController extends Controller
{
    public function store(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'nullable|integer|min:1',
            'sales_start_at' => 'nullable|date',
            'sales_end_at' => 'nullable|date|after:sales_start_at',
        ]);

        $event->ticketTypes()->create($validated + ['is_active' => true, 'quantity_sold' => 0]);

        return back()->with('success', 'Ticket type added');
    }

    public function destroy(Event $event, TicketType $ticketType)
    {
        $this->authorize('update', $event);
        abort_if($ticketType->event_id !== $event->id, 404);

        if ($ticketType->registrations()->exists()) {
            return back()->with('error', 'Cannot delete a ticket type that already has registrations');
        }

        $ticketType->delete();

        return back()->with('success', 'Ticket type deleted');
    }
}
