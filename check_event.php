<?php

// Check event details including ticket types

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;

echo "\n=== EVENT DETAILS CHECK ===\n\n";

$event = Event::with(['tenant', 'ticketTypes'])->find(1);

if (!$event) {
    echo "❌ Event ID 1 not found!\n";
    exit;
}

echo "Event: {$event->title}\n";
echo "Slug: {$event->slug}\n";
echo "Status: {$event->status}\n";
echo "Tenant: {$event->tenant->name}\n\n";

echo "Ticket Types: ";
if ($event->ticketTypes->isEmpty()) {
    echo "❌ NO TICKET TYPES!\n";
    echo "\n⚠️  This is the problem! Events need at least one ticket type.\n\n";
    echo "Creating a default FREE ticket type...\n";
    
    $ticketType = \App\Models\TicketType::create([
        'event_id' => $event->id,
        'name' => 'General Admission',
        'description' => 'General admission ticket',
        'price' => 0.00,
        'quantity_available' => $event->capacity ?? 100,
        'quantity_sold' => 0,
        'is_free' => true,
        'sale_starts_at' => now(),
        'sale_ends_at' => $event->start_at,
    ]);
    
    echo "✅ Created ticket type: {$ticketType->name}\n";
    echo "✅ Price: FREE\n";
    echo "✅ Quantity: " . ($event->capacity ?? 100) . "\n\n";
    echo "Now try the registration URL again!\n";
} else {
    echo "✅ Found {$event->ticketTypes->count()} ticket type(s):\n";
    foreach ($event->ticketTypes as $ticket) {
        echo "  - {$ticket->name} (" . ($ticket->is_free ? 'FREE' : '$' . $ticket->price) . ")\n";
    }
}

echo "\n📋 Registration URL:\n";
echo "   http://localhost:8000/events/{$event->slug}\n\n";
