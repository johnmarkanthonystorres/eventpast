<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Registration;
use App\Models\ReminderLog;
use App\Notifications\EventReminder;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

class SendEventReminders extends Command
{
    protected $signature = 'reminders:send {--dry-run : List what would be sent without sending}';

    protected $description = 'Send 7-day, 1-day and 2-hour reminders to confirmed registrants';

    public function handle(): int
    {
        $now = now();
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;

        // start_at is wall-clock in the event's timezone, so offsets up to
        // +/-14h separate the stored value from the real instant. Pull a
        // padded range and decide precisely in PHP.
        $events = Event::query()
            ->where('status', 'published')
            ->whereBetween('start_at', [
                $now->copy()->subHours(15),
                $now->copy()->addDays(7)->addHours(15),
            ])
            ->get();

        foreach ($events as $event) {
            $type = $this->dueType($event->startsAtInstant(), $now);

            if ($type === null) {
                continue;
            }

            $registrations = Registration::query()
                ->with(['attendee', 'event'])
                ->where('event_id', $event->id)
                ->where('status', 'confirmed')
                ->whereDoesntHave('checkIn')
                ->whereNotExists(function ($q) use ($type) {
                    $q->selectRaw('1')
                        ->from('reminder_logs')
                        ->whereColumn('reminder_logs.registration_id', 'registrations.id')
                        ->where('reminder_logs.type', $type);
                })
                ->get();

            foreach ($registrations as $registration) {
                if (! $registration->attendee?->email) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("[dry-run] {$type} -> {$registration->attendee->email} ({$event->title})");
                    $sent++;
                    continue;
                }

                if ($this->sendOnce($registration, $type)) {
                    $sent++;
                }
            }
        }

        $this->info(($dryRun ? 'Would send ' : 'Sent ') . "{$sent} reminder(s).");

        return self::SUCCESS;
    }

    /**
     * The single reminder type that applies right now, or null.
     *
     * Only the tightest window the event is currently inside is returned, so
     * a late registrant is not sent a "one week to go" email the day before.
     */
    private function dueType(Carbon $startsAt, Carbon $now): ?string
    {
        if ($startsAt->lessThanOrEqualTo($now)) {
            return null;
        }

        $minutesUntil = $now->diffInMinutes($startsAt);
        $due = null;

        foreach (ReminderLog::WINDOWS as $type => $minutes) {
            if ($minutesUntil <= $minutes) {
                $due = $type;
            }
        }

        return $due;
    }

    /**
     * Claim the (registration, type) slot first, then notify. The unique
     * index makes this safe against overlapping scheduler runs.
     */
    private function sendOnce(Registration $registration, string $type): bool
    {
        try {
            $log = ReminderLog::create([
                'registration_id' => $registration->id,
                'type' => $type,
                'sent_at' => now(),
            ]);
        } catch (QueryException $e) {
            return false; // another run already claimed it
        }

        try {
            $registration->attendee->notify(new EventReminder($registration, $type));
        } catch (\Throwable $e) {
            $log->delete(); // release so the next run retries
            report($e);
            $this->error("Failed for registration {$registration->id}: {$e->getMessage()}");

            return false;
        }

        return true;
    }
}
