<?php

namespace App\Features\Customers\Notifications;

use App\Features\Tracking\Services\EmailBranding;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountEmailChange extends Notification
{
    public function __construct(public string $firstName, public string $newEmail, public ?string $confirmationUrl = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = ['firstName' => $this->firstName, 'newEmail' => $this->newEmail, 'confirmationUrl' => $this->confirmationUrl,
            'branding' => app(EmailBranding::class)->for(null)];

        return (new MailMessage)->subject($this->confirmationUrl ? 'Confirm your new DancePro email address' : 'Your DancePro email address has changed')
            ->view(['html' => 'emails.tracking.concert-message', 'text' => 'emails.customers.change-email-text'], $data + [
                'messageHtml' => view('emails.customers.change-email-content', $data)->render(), 'accountUrl' => null, 'openTrackingUrl' => null,
            ]);
    }
}
