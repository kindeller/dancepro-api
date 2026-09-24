<?php

namespace App\Features\Customers\Notifications;

use App\Features\Customers\Models\CustomerSupportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerSupportRequestUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly CustomerSupportRequest $supportRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->supportRequest;
        $message = (new MailMessage)->subject('DancePro media help request updated')
            ->greeting('Hi '.$notifiable->name.',');

        if ($request->status === 'approved') {
            $message->line('Your media download access has been restored.')
                ->line('Downloads are available through '.$request->entitlement?->expires_at?->format('j F Y').'.');
        } elseif ($request->status === 'declined') {
            $message->line('We reviewed your download reactivation request and could not restore access at this time.');
        } else {
            $message->line('Your DancePro media help request has been resolved.');
        }

        if ($request->response_note) {
            $message->line($request->response_note);
        }

        return $message->action('View help request', route('customer.media.help'))
            ->line('Reply to this email if you still need assistance.');
    }
}
