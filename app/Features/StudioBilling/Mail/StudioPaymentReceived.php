<?php

namespace App\Features\StudioBilling\Mail;

use App\Features\Tracking\Services\EmailBranding;
use App\Features\Tracking\Services\EmailContentFormatter;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StudioPaymentReceived extends Mailable
{
    public function __construct(public readonly array $receipt) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->receipt['subject']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tracking.concert-message', text: 'emails.studio-billing.payment-text', with: [
            'branding' => app(EmailBranding::class)->for(null), 'openTrackingUrl' => null, 'accountUrl' => null,
            'messageHtml' => view('emails.studio-billing.payment-content', ['receipt' => $this->receipt,
                'bodyHtml' => app(EmailContentFormatter::class)->html($this->receipt['body'])])->render(),
        ]);
    }
}
