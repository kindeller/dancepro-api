<?php

namespace App\Features\Bookings\Mail;

use App\Features\Tracking\Services\EmailBranding;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingSubmitted extends Mailable
{
    public function __construct(public readonly array $receipt) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->receipt['subject']);
    }

    public function content(): Content
    {
        $data = $this->receipt['content'];

        return new Content(view: 'emails.tracking.concert-message', text: 'emails.bookings.submitted-text', with: $data + [
            'branding' => app(EmailBranding::class)->for(null),
            'messageHtml' => view('emails.bookings.submitted-content', $data)->render(),
            'openTrackingUrl' => null,
        ]);
    }
}
