<?php

namespace App\Features\Customers\Notifications;

use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Tracking\Services\EmailContentFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class CustomerConversationReply extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly CustomerSupportRequest $conversation, public readonly string $body) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('DancePro: '.$this->conversation->subject)
            ->greeting('Hi '.$notifiable->emailGreetingName().',')->line(new HtmlString(app(EmailContentFormatter::class)->html($this->body)))
            ->action('Read and reply', route('customer.messages.show', $this->conversation));
    }
}
