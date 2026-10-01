<?php

namespace App\Features\Customers\Notifications;

use App\Features\Tracking\Services\EmailContentFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class CustomerAccountMessage extends Notification
{
    use Queueable;

    public function __construct(private readonly string $subject, private readonly string $body, private readonly ?string $conversationUuid = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->subject)->greeting('Hi '.$notifiable->emailGreetingName().',')->line(new HtmlString(app(EmailContentFormatter::class)->html($this->body)))->action($this->conversationUuid ? 'Read and reply' : 'Open your DancePro account', $this->conversationUuid ? route('customer.messages.show', $this->conversationUuid) : route('customer.media.index'));
    }
}
