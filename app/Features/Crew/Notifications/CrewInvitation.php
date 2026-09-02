<?php

namespace App\Features\Crew\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CrewInvitation extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
            'onboarding' => 1,
        ]);

        return (new MailMessage)
            ->subject('Welcome to DancePro Crew')
            ->greeting("Hi {$notifiable->name},")
            ->line('You have been invited to the DancePro Crew Hub.')
            ->line('Use this secure link to choose your password, sign in and complete your crew profile.')
            ->action('Set up my DancePro account', $url)
            ->line('If the link expires, ask DancePro to send you a new invitation.');
    }
}
