<?php

namespace App\Features\Concerts\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConcertFamilyVerification extends Notification
{
    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Your DancePro family viewing code')
            ->greeting('Verify your family invitation')
            ->line('Your verification code is: '.$this->code)
            ->line('It expires in 10 minutes. Enter it only on the DancePro page where you requested it.')
            ->line('If you did not request this code, you can ignore this email. Do not forward this code.');
    }
}
