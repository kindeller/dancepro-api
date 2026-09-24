<?php

namespace App\Features\Tracking\Mail;

use App\Features\Tracking\Services\EmailBranding;
use App\Features\Tracking\Services\EmailContentFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConcertTrackerMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $messageSubject, public readonly string $messageBody, public readonly ?string $openTrackingUrl = null, public readonly ?string $accountUrl = null, public readonly ?int $senderUserId = null) {}

    public function envelope(): Envelope
    {
        $branding = app(EmailBranding::class)->for($this->senderUserId);

        return new Envelope(subject: $this->messageSubject, replyTo: $branding['replyAddress'] ? [new Address($branding['replyAddress'])] : []);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tracking.concert-message', with: ['messageBody' => $this->messageBody, 'messageHtml' => app(EmailContentFormatter::class)->html($this->messageBody), 'openTrackingUrl' => $this->openTrackingUrl, 'accountUrl' => $this->accountUrl, 'branding' => app(EmailBranding::class)->for($this->senderUserId)]);
    }
}
