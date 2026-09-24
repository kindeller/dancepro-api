<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\GuestGalleryRegistration;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestGalleryRegistrations
{
    public function __construct(private readonly GuestGalleryCatalogue $catalogue) {}

    public function register(SchedulingEvent $event, string $email): GuestGalleryRegistration
    {
        if (! $this->catalogue->acceptsRegistration($event)) {
            throw ValidationException::withMessages(['email' => 'Release notifications are not available for this event.']);
        }
        $email = mb_strtolower(trim($email));

        return DB::transaction(function () use ($event, $email): GuestGalleryRegistration {
            return GuestGalleryRegistration::updateOrCreate(
                ['scheduling_event_id' => $event->id, 'email_hash' => hash('sha256', $email)],
                ['email' => $email, 'consent_at' => now(), 'notified_at' => null, 'unsubscribed_at' => null],
            );
        });
    }

    public function unsubscribe(GuestGalleryRegistration $registration): void
    {
        $registration->update(['unsubscribed_at' => now()]);
    }
}
