<?php

namespace App\Features\Families\Notifications;

use App\Features\Tracking\Services\EmailBranding;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FamilyInvitation extends Notification
{
    public function __construct(public string $managerName, public string $firstName, public string $invitationUrl, public bool $viewMedia, public bool $purchase) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = ['invitation' => $this, 'branding' => app(EmailBranding::class)->for(null)];

        return (new MailMessage)->subject('You have been invited to a DancePro family')
            ->view(['html' => 'emails.tracking.concert-message', 'text' => 'emails.families.invitation-text'], $data + [
                'messageHtml' => view('emails.families.invitation-content', $data)->render(), 'accountUrl' => null, 'openTrackingUrl' => null,
            ]);
    }
}
