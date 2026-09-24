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

class StudioConversationReply extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $replySubject, public string $replyBody, public string $accountUrl, public ?int $senderUserId = null) {}

    public function envelope(): Envelope
    {
        $branding = app(EmailBranding::class)->for($this->senderUserId);

        return new Envelope(subject: $this->replySubject, replyTo: $branding['replyAddress'] ? [new Address($branding['replyAddress'])] : []);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tracking.studio-conversation-reply', with: ['replyHtml' => app(EmailContentFormatter::class)->html($this->replyBody), 'branding' => app(EmailBranding::class)->for($this->senderUserId)]);
    }
}
