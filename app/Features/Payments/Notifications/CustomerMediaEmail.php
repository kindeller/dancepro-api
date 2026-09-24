<?php

namespace App\Features\Payments\Notifications;

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

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->subject);
        foreach (preg_split('/\R{2,}/', trim($this->body)) ?: [] as $paragraph) {
            $message->line($paragraph);
        }

        return $message->action($this->actionLabel, $this->actionUrl)
            ->line('If you need help, reply to this email and include your order number.');
    }
}
