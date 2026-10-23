<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Registration;
use App\Notifications\EventReminder;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendEventReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reminders:send {--type=all : Type of reminder to send (7day, 1day, 2hour, all)}';

    /**
     * The console command description.
     */
    protected $description = 'Send event reminders to registered attendees';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        
        $this->info('Starting reminder process...');
        
        $counts = [
            '7day' => 0,
            '1day' => 0,
            '2hour' => 0,
        ];
        
        if ($type === 'all' || $type === '7day') {
            $counts['7day'] = $this->send7DayReminders();
        }
        
        if ($type === 'all' || $type === '1day') {
            $counts['1day'] = $this->send1DayReminders();
        }
        
        if ($type === 'all' || $type === '2hour') {
            $counts['2hour'] = $this->send2HourReminders();
        }
        
        $this->info('✓ Reminders sent successfully!');
        $this->table(
            ['Reminder Type', 'Count'],
            [
                ['7 Days Before', $counts['7day']],
                ['1 Day Before', $counts['1day']],
                ['2 Hours Before', $counts['2hour']],
                ['Total', array_sum($counts)],
            ]
        );
        
        return Command::SUCCESS;
    }
    
    /**
     * Send reminders 7 days before event
     */
    protected function send7DayReminders(): int
    {
        $targetTime = now()->addDays(7);
        
        $events = Event::where('status', 'published')
            ->whereBetween('start_at', [
                $targetTime->copy()->subHours(1),
                $targetTime->copy()->addHours(1)
            ])
            ->get();
        
        $count = 0;
        
        foreach ($events as $event) {
            $registrations = $event->registrations()
                ->where('status', 'confirmed')
                ->whereDoesntHave('attendee', function($query) use ($event) {
                    $query->whereJsonContains('notifications_sent', [
                        'event_id' => $event->id,
                        'type' => '7day_reminder'
                    ]);
                })
                ->with('attendee')
                ->get();
            
            foreach ($registrations as $registration) {
                try {
                    $registration->attendee->notify(new EventReminder($registration, '7day'));
                    $this->markReminderSent($registration, '7day');
                    $count++;
                } catch (\Exception $e) {
                    $this->error("Failed to send 7-day reminder for registration {$registration->id}: {$e->getMessage()}");
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Send reminders 1 day before event
     */
    protected function send1DayReminders(): int
    {
        $targetTime = now()->addDay();
        
        $events = Event::where('status', 'published')
            ->whereBetween('start_at', [
                $targetTime->copy()->subHours(1),
                $targetTime->copy()->addHours(1)
            ])
            ->get();
        
        $count = 0;
        
        foreach ($events as $event) {
            $registrations = $event->registrations()
                ->where('status', 'confirmed')
                ->whereDoesntHave('attendee', function($query) use ($event) {
                    $query->whereJsonContains('notifications_sent', [
                        'event_id' => $event->id,
                        'type' => '1day_reminder'
                    ]);
                })
                ->with('attendee')
                ->get();
            
            foreach ($registrations as $registration) {
                try {
                    $registration->attendee->notify(new EventReminder($registration, '1day'));
                    $this->markReminderSent($registration, '1day');
                    $count++;
                } catch (\Exception $e) {
                    $this->error("Failed to send 1-day reminder for registration {$registration->id}: {$e->getMessage()}");
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Send reminders 2 hours before event
     */
    protected function send2HourReminders(): int
    {
        $targetTime = now()->addHours(2);
        
        $events = Event::where('status', 'published')
            ->whereBetween('start_at', [
                $targetTime->copy()->subMinutes(30),
                $targetTime->copy()->addMinutes(30)
            ])
            ->get();
        
        $count = 0;
        
        foreach ($events as $event) {
            $registrations = $event->registrations()
                ->where('status', 'confirmed')
                ->whereDoesntHave('attendee', function($query) use ($event) {
                    $query->whereJsonContains('notifications_sent', [
                        'event_id' => $event->id,
                        'type' => '2hour_reminder'
                    ]);
                })
                ->with('attendee')
                ->get();
            
            foreach ($registrations as $registration) {
                try {
                    $registration->attendee->notify(new EventReminder($registration, '2hour'));
                    $this->markReminderSent($registration, '2hour');
                    $count++;
                } catch (\Exception $e) {
                    $this->error("Failed to send 2-hour reminder for registration {$registration->id}: {$e->getMessage()}");
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Mark reminder as sent to avoid duplicates
     */
    protected function markReminderSent(Registration $registration, string $type): void
    {
        $attendee = $registration->attendee;
        $sentNotifications = $attendee->notifications_sent ?? [];
        
        $sentNotifications[] = [
            'event_id' => $registration->event_id,
            'type' => "{$type}_reminder",
            'sent_at' => now()->toDateTimeString(),
        ];
        
        $attendee->update(['notifications_sent' => $sentNotifications]);
    }
}
