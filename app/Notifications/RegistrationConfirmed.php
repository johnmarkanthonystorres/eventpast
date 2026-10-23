<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public $registration;

    /**
     * Create a new notification instance.
     */
    public function __construct(Registration $registration)
    {
        $this->registration = $registration;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
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
        $confirmationUrl = route('registrations.confirmation', $this->registration->id);
        
        return (new MailMessage)
            ->subject('Registration Confirmed: ' . $event->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your registration for **' . $event->title . '** has been confirmed.')
            ->line('**Event Details:**')
            ->line('📅 ' . $event->start_at->format('l, F j, Y \a\t g:i A') . ' (' . $event->timezone . ')')
            ->line('📍 ' . ($event->venue_name ?: ($event->online_link ? 'Online Event' : 'TBA')))
            ->action('View Your Ticket', $confirmationUrl)
            ->line('Please bring your QR code ticket to check-in at the event.')
            ->line('We look forward to seeing you there!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'registration_id' => $this->registration->id,
            'event_id' => $this->registration->event_id,
        ];
    }
}
