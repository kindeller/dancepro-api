<?php

namespace App\Features\Customers\Notifications;

use App\Features\Customers\Models\GuestGalleryRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class GuestGalleryReleased extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly GuestGalleryRegistration $registration) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->registration->event;
        $open = URL::temporarySignedRoute('guest-galleries.notifications.open', now()->addMonths(3), ['registration' => $this->registration]);
        $unsubscribe = URL::temporarySignedRoute('guest-galleries.notifications.unsubscribe', now()->addMonths(3), ['registration' => $this->registration]);

        return (new MailMessage)
            ->subject($event->name.' media is now available')
            ->greeting('Your DancePro gallery is ready')
            ->line($event->name.' now has media available to view.')
            ->action('Open the gallery', $open)
            ->line('Your email will be filled in. Enter the gallery login code supplied for the event to continue.')
            ->line('No longer interested? '.$unsubscribe);
    }
}
