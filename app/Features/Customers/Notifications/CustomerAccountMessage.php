<?php

namespace App\Features\Customers\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerAccountMessage extends Notification
{
    use Queueable;

    public function __construct(private readonly string $subject, private readonly string $body) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->subject)->greeting('Hi '.$notifiable->name.',')->line($this->body)->action('Open your DancePro account', route('customer.media.index'));
    }
}
