<?php

// Run this to fix events without slugs
// Usage: php fix_event_slugs.php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;
use Illuminate\Support\Str;

$events = Event::whereNull('slug')->orWhere('slug', '')->get();

if ($events->isEmpty()) {
    echo "✅ All events have slugs!\n";
    exit;
}

echo "Found {$events->count()} events without slugs. Fixing...\n\n";

foreach ($events as $event) {
    $event->slug = Str::slug($event->title);
    $event->save();
    echo "✅ Event #{$event->id}: {$event->title} → {$event->slug}\n";
}

echo "\n✅ Done! All events now have slugs.\n";
