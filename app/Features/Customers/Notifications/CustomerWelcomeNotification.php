<?php

namespace App\Features\Customers\Notifications;

use App\Features\Customers\Models\CustomerAccountEmail;
use App\Features\Tracking\Services\EmailBranding;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerWelcomeNotification extends Notification
{
    public function __construct(public readonly CustomerAccountEmail $email) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->email->metadata['welcome'];

        return (new MailMessage)->subject($this->email->subject)
            ->view(['html' => 'emails.tracking.concert-message', 'text' => 'emails.customers.welcome-text'], $data + [
                'branding' => app(EmailBranding::class)->for(null),
                'messageHtml' => view('emails.customers.welcome-content', $data)->render(),
                'openTrackingUrl' => null,
            ]);
    }
}
