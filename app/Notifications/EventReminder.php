<?php

namespace App\Notifications;

use App\Models\Registration;
use App\Models\ReminderLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Registration $registration,
        public string $type,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->registration->event;
        $ticketUrl = route('registrations.confirmation', $this->registration->id);

        $when = $event->start_at->format('l, F j, Y \a\t g:i A') . ' (' . $event->timezone . ')';
        $where = $event->venue_name ?: ($event->online_link ? 'Online event' : 'Location to be announced');

        $mail = (new MailMessage)
            ->subject($this->subject($event->title))
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line($this->intro($event->title))
            ->line('**When:** ' . $when)
            ->line('**Where:** ' . $where);

        if ($event->venue_address) {
            $mail->line('**Address:** ' . $event->venue_address);
        }

        if ($event->online_link) {
            $mail->line('**Join link:** ' . $event->online_link);
        }

        return $mail
            ->action('View Your Ticket', $ticketUrl)
            ->line('Have your QR code ready at check-in.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'registration_id' => $this->registration->id,
            'event_id' => $this->registration->event_id,
            'type' => $this->type,
        ];
    }

    private function subject(string $title): string
    {
        return match ($this->type) {
            ReminderLog::TYPE_WEEK => "One week to go: {$title}",
            ReminderLog::TYPE_DAY => "Tomorrow: {$title}",
            default => "Starting soon: {$title}",
        };
    }

    private function intro(string $title): string
    {
        return match ($this->type) {
            ReminderLog::TYPE_WEEK => "**{$title}** is one week away. Mark your calendar!",
            ReminderLog::TYPE_DAY => "**{$title}** is happening tomorrow.",
            default => "**{$title}** starts in about 2 hours.",
        };
    }
}
