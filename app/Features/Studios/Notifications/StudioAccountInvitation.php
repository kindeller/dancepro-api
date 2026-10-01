<?php

namespace App\Features\Studios\Notifications;

use App\Features\Studios\Models\Studio;
use App\Features\Studios\Services\StudioAccessMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudioAccountInvitation extends Notification
{
    use Queueable;

    public function __construct(public Studio $studio, public bool $existingAccount, public bool $active = false, public ?string $firstName = null, public string $role = 'office_contact') {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return app(StudioAccessMessage::class)->for($this->studio, $notifiable->routeNotificationFor('mail'),
            $this->existingAccount, $this->active, $this->firstName, $this->role);
    }
}
