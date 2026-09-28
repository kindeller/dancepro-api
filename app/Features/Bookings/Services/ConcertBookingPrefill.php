<?php

namespace App\Features\Bookings\Services;

use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Studios\Models\Studio;
use App\Models\User;

class ConcertBookingPrefill
{
    public function __construct(private readonly StudioCommunicationHistory $access) {}

    public function forUser(?User $user, ?string $studioUuid): array
    {
        $studio = null;
        if ($studioUuid) {
            abort_unless($user, 403);
            $studio = Studio::query()->with('contacts')->where('uuid', $studioUuid)->firstOrFail();
            abort_unless($this->access->canView($user, $studio), 403);
        }
        $contact = $studio?->contacts->first(fn ($contact) => collect($contact->emailAddresses())
            ->contains(fn ($email) => mb_strtolower(trim($email)) === mb_strtolower(trim($user->email))));

        return [
            'bookingStudio' => $studio,
            'prefill' => [
                'studio_name' => $studio?->name,
                'contact_name' => $contact?->name ?: ($studio?->contact_name ?: $user?->name),
                'contact_email' => $contact ? $user->email : ($studio?->contact_email ?: $user?->email),
                'contact_phone' => $contact?->phone ?: ($studio?->contact_phone ?: $user?->customerProfile?->phone),
            ],
        ];
    }
}
