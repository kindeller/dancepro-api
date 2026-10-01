<?php

namespace App\Features\Customers\Notifications;

use App\Features\Customers\Models\GuestGalleryRegistration;
use App\Models\User;
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
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $registration = $this->registration->fresh();

        return $registration && ! $registration->unsubscribed_at
            && ! $registration->event?->concertBookingItem?->concert?->mediaCollections()->whereHas('concertPhotoReleaseGroups', fn ($groups) => $groups->whereNotNull('released_at'))->exists()
            && (! $registration->guest_studio_subscription_id || ($registration->studioSubscription && ! $registration->studioSubscription->unsubscribed_at));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->registration->event;
        $open = URL::temporarySignedRoute('guest-galleries.notifications.open', now()->addMonths(3), ['registration' => $this->registration]);
        $unsubscribe = $this->registration->guest_studio_subscription_id
            ? URL::temporarySignedRoute('guest-galleries.studios.unsubscribe', now()->addMonths(3), ['subscription' => $this->registration->studioSubscription])
            : URL::temporarySignedRoute('guest-galleries.notifications.unsubscribe', now()->addMonths(3), ['registration' => $this->registration]);

        return (new MailMessage)
            ->subject($event->name.' media is now available')
            ->greeting('Your DancePro gallery is ready')
            ->line($event->name.' now has media available to view.')
            ->action('Open the gallery', $open)
            ->line('Your email will be filled in. Enter the gallery access code supplied for the event to continue.')
            ->line('No longer interested? '.$unsubscribe);
    }

    public function toArray(object $notifiable): array
    {
        $event = $this->registration->event;

        return [
            'title' => 'Gallery ready',
            'body' => $event->name.' media is now available.',
            'url' => URL::temporarySignedRoute('guest-galleries.notifications.open', now()->addMonths(3), ['registration' => $this->registration]),
            'event_uuid' => $event->uuid,
        ];
    }
}
