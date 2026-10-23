<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminder extends Notification implements ShouldQueue
{
    use Queueable;

    protected Registration $registration;
    protected string $reminderType;

    /**
     * Create a new notification instance.
     */
    public function __construct(Registration $registration, string $reminderType)
    {
        $this->registration = $registration;
        $this->reminderType = $reminderType;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->registration->event;
        $timeUntil = $this->getTimeUntilText();
        $subject = $this->getSubject();
        
        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hi {$notifiable->name}!")
            ->line($this->getIntroLine())
            ->line("**Event:** {$event->title}")
            ->line("**When:** {$this->formatEventTime()}")
            ->line("**Where:** {$this->getLocationText()}");
        
        // Add location details
        if ($event->venue_address) {
            $message->line("**Address:** {$event->venue_address}");
        }
        
        if ($event->online_link) {
            $message->action('Join Online', $event->online_link);
        }
        
        // Add QR code reminder for in-person events
        if (!$event->online_link) {
            $message->line('**Remember to bring your QR code ticket for check-in!**');
            $message->action('View Your Ticket', route('registrations.confirmation', $this->registration->id));
        }
        
        // Add specific reminders based on type
        $message = $this->addTypeSpecificContent($message);
        
        $message->line('We look forward to seeing you there!')
            ->salutation('Best regards, ' . config('app.name'));
        
        return $message;
    }

    /**
     * Get the subject line based on reminder type
     */
    protected function getSubject(): string
    {
        $event = $this->registration->event;
        
        return match($this->reminderType) {
            '7day' => "Reminder: {$event->title} is in 7 days",
            '1day' => "Tomorrow: {$event->title}",
            '2hour' => "Starting Soon: {$event->title} in 2 hours",
            default => "Reminder: {$event->title}",
        };
    }

    /**
     * Get the intro line based on reminder type
     */
    protected function getIntroLine(): string
    {
        $timeUntil = $this->getTimeUntilText();
        
        return match($this->reminderType) {
            '7day' => "This is a friendly reminder that you're registered for an event happening in one week!",
            '1day' => "Just a quick reminder that your event is tomorrow! We can't wait to see you.",
            '2hour' => "Your event is starting in just 2 hours! Time to get ready.",
            default => "This is a reminder about your upcoming event.",
        };
    }

    /**
     * Add type-specific content to the message
     */
    protected function addTypeSpecificContent(MailMessage $message): MailMessage
    {
        $event = $this->registration->event;
        
        switch($this->reminderType) {
            case '7day':
                $message->line('**What to do now:**')
                    ->line('• Add the event to your calendar')
                    ->line('• Invite friends who might be interested')
                    ->line('• Prepare any questions you\'d like to ask');
                break;
                
            case '1day':
                $message->line('**Tomorrow\'s checklist:**')
                    ->line('• Set a reminder for the event time')
                    ->line('• Plan your route/travel time')
                    ->line('• Have your ticket QR code ready');
                if ($event->online_link) {
                    $message->line('• Test your internet connection and camera/mic');
                }
                break;
                
            case '2hour':
                $message->line('**Get ready now:**');
                if ($event->online_link) {
                    $message->line('• Join the event using the button above')
                        ->line('• Make sure your camera and microphone work')
                        ->line('• Find a quiet space for the event');
                } else {
                    $message->line('• Head to the venue now to arrive on time')
                        ->line('• Have your ticket QR code ready for check-in')
                        ->line('• Allow extra time for parking/traffic');
                }
                break;
        }
        
        return $message;
    }

    /**
     * Get human-readable time until event
     */
    protected function getTimeUntilText(): string
    {
        return match($this->reminderType) {
            '7day' => '7 days',
            '1day' => '1 day',
            '2hour' => '2 hours',
            default => 'soon',
        };
    }

    /**
     * Format event time
     */
    protected function formatEventTime(): string
    {
        $event = $this->registration->event;
        $start = \Carbon\Carbon::parse($event->start_at);
        $end = \Carbon\Carbon::parse($event->end_at);
        
        return $start->format('l, F j, Y \a\t g:i A') . ' - ' . $end->format('g:i A') . ' (' . $event->timezone . ')';
    }

    /**
     * Get location text
     */
    protected function getLocationText(): string
    {
        $event = $this->registration->event;
        
        if ($event->online_link) {
            return 'Online Event';
        }
        
        return $event->venue_name ?: 'Location TBA';
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->registration->event_id,
            'registration_id' => $this->registration->id,
            'reminder_type' => $this->reminderType,
            'event_title' => $this->registration->event->title,
        ];
    }
}
