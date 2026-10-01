<?php

namespace App\Features\Payments\Notifications;

use App\Features\Customers\Services\CustomerCommunicationPreferences;
use App\Features\Payments\Models\CustomerMediaNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerMediaEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $deliveryId,
        private readonly string $subject,
        private readonly string $body,
        private readonly string $actionLabel,
        private readonly string $actionUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User) {
            return true;
        }
        $user = $notifiable->fresh();
        $delivery = CustomerMediaNotification::find($this->deliveryId);
        $preference = match ($delivery?->type) {
            'media_ready' => 'media_ready_email',
            'expiry_reminder' => 'expiry_reminders_email',
            default => null,
        };
        $send = $user && ! $user->anonymized_at && ! $user->merged_into_user_id
            && (! $preference || CustomerCommunicationPreferences::enabled($user, $preference));
        if (! $send) {
            $delivery?->update(['status' => 'suppressed']);
        }

        return (bool) $send;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->subject);
        if ($notifiable instanceof User) {
            $message->greeting('Hi '.$notifiable->emailGreetingName().',');
        }
        foreach (preg_split('/\R{2,}/', trim($this->body)) ?: [] as $paragraph) {
            $message->line($paragraph);
        }

        return $message->action($this->actionLabel, $this->actionUrl)
            ->line('If you need help, reply to this email and include your order number.');
    }
}
