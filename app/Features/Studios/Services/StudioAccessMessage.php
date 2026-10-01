<?php

namespace App\Features\Studios\Services;

use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Services\EmailBranding;
use Illuminate\Notifications\Messages\MailMessage;

class StudioAccessMessage
{
    public function __construct(private readonly EmailBranding $branding) {}

    public function for(Studio $studio, string $email, bool $existingAccount, bool $active, ?string $firstName, string $role): MailMessage
    {
        $branding = $this->branding->for(null);
        $data = [
            'studioName' => $studio->name,
            'email' => $email,
            'firstName' => $firstName,
            'active' => $active,
            'existingAccount' => $existingAccount,
            'roleLabel' => match ($role) {
                'owner' => 'Owner', 'director' => 'Director', default => 'Office contact'
            },
            'canRelease' => in_array($role, ['owner', 'director'], true),
            'actionUrl' => $active ? route('customer.studio-communications.index', $studio)
                : ($existingAccount ? route('login') : route('customer.register', ['email' => $email])),
            'actionLabel' => $active ? 'Open Studio Hub' : ($existingAccount ? 'Sign in to DancePro' : 'Create your DancePro account'),
            'supportEmail' => $branding['replyAddress'] ?: 'info@dancepro.com.au',
            'branding' => $branding,
        ];

        return (new MailMessage)
            ->subject(($active ? 'Studio Hub access added: ' : 'Your Studio Hub invitation: ').$studio->name)
            ->replyTo($data['supportEmail'], 'DancePro')
            ->view(['html' => 'emails.tracking.concert-message', 'text' => 'emails.studios.account-access-text'], [
                ...$data,
                'messageHtml' => view('emails.studios.account-access-content', $data)->render(),
                'accountUrl' => null,
                'openTrackingUrl' => null,
            ]);
    }
}
