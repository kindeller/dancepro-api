<?php

namespace App\Features\Customers\Services;

use App\Features\Tracking\Services\EmailBranding;
use Illuminate\Notifications\Messages\MailMessage;

class EmailVerificationMessage
{
    public function __construct(private readonly EmailBranding $branding) {}

    public function for(object $user, string $verificationUrl): MailMessage
    {
        $data = [
            'name' => $user->emailGreetingName(),
            'verificationUrl' => $verificationUrl,
            'expiresInMinutes' => (int) config('auth.verification.expire', 60),
            'branding' => $this->branding->for(null),
        ];

        return (new MailMessage)
            ->subject('Verify your email address')
            ->view([
                'html' => 'emails.tracking.concert-message',
                'text' => 'emails.customers.verify-email-text',
            ], array_merge($data, [
                'messageHtml' => view('emails.customers.verify-email-content', $data)->render(),
                'accountUrl' => null,
                'openTrackingUrl' => null,
            ]));
    }
}
