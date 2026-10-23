<?php

// Quick script to list all events and their slugs
// Usage: Run from Laragon terminal

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;

echo "\n=== ALL EVENTS IN DATABASE ===\n\n";

$events = Event::with('tenant')->get();

if ($events->isEmpty()) {
    echo "❌ No events found in database!\n";
    echo "\nYou need to create an event first:\n";
    echo "1. Login: http://localhost:8000/login\n";
    echo "2. Click 'Create Event'\n";
    echo "3. Fill in details and save\n\n";
    exit;
}

echo "Found {$events->count()} event(s):\n\n";

foreach ($events as $event) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "ID: {$event->id}\n";
    echo "Title: {$event->title}\n";
    echo "Slug: " . ($event->slug ?: '❌ NO SLUG!') . "\n";
    echo "Status: {$event->status}\n";
    echo "Tenant: {$event->tenant->name}\n";
    echo "\n📋 Admin URL (use ID):\n";
    echo "   http://localhost:8000/events/{$event->id}\n";
    
    if ($event->slug) {
        echo "\n🎫 Registration URL (use SLUG):\n";
        echo "   http://localhost:8000/events/{$event->slug}\n";
    } else {
        echo "\n❌ NO SLUG - Run fix_event_slugs.php\n";
    }
    
    if ($event->status !== 'published') {
        echo "\n⚠️  Event is '{$event->status}' - must be 'published' for registration!\n";
    }
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "✅ Use the Registration URLs above to test!\n\n";
