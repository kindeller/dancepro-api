<?php

namespace App\Features\Studios\Notifications;

use App\Features\Studios\Models\Studio;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudioAccountInvitation extends Notification
{
    use Queueable;

    public function __construct(public Studio $studio, public bool $existingAccount) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->existingAccount ? route('customer.login') : route('customer.register', ['email' => $notifiable->routeNotificationFor('mail')]);

        return (new MailMessage)->subject('Access '.$this->studio->name.' in DancePro')
            ->line('You have been invited to access '.$this->studio->name.' in DancePro.')
            ->line('Your verified account email must match this invitation address.')
            ->action($this->existingAccount ? 'Log into DancePro' : 'Create your DancePro account', $url)
            ->line('Once signed in, you can view studio communications and event details.');
    }
}
