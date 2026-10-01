<?php

namespace App\Features\Bookings\Mail;

use App\Features\Bookings\Models\BookingStatusNotification;
use App\Features\Tracking\Services\EmailBranding;
use App\Features\Tracking\Services\EmailContentFormatter;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingStatusMessage extends Mailable
{
    public function __construct(public readonly BookingStatusNotification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notification->subject);
    }

    public function content(): Content
    {
        $studio = $this->notification->booking->studio;

        return new Content(view: 'emails.tracking.concert-message', text: 'emails.bookings.status-text', with: [
            'branding' => app(EmailBranding::class)->for($this->notification->sent_by_user_id),
            'messageHtml' => app(EmailContentFormatter::class)->html($this->notification->body),
            'body' => $this->notification->body,
            'accountUrl' => $studio ? route('customer.studio-communications.index', $studio) : route('login'),
            'openTrackingUrl' => null,
        ]);
    }
}
