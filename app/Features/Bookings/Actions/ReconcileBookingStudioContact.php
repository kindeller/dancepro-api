<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Services\ReviewBookingStudioContact;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconcileBookingStudioContact
{
    public function __construct(private readonly ReviewBookingStudioContact $review) {}

    public function execute(ConcertBooking $booking, array $data): StudioContact
    {
        return DB::transaction(function () use ($booking, $data): StudioContact {
            $studio = Studio::query()->with('contacts')->where('uuid', $data['studio_uuid'])->firstOrFail();
            $contact = $this->review->findContact($booking, $studio);

            if ($data['action'] === 'add') {
                $contact = $studio->contacts()->create([
                    'name' => $booking->contact_name,
                    'role' => $data['role'] ?? null,
                    'emails' => [mb_strtolower($booking->contact_email)],
                    'phone' => $booking->contact_phone,
                    'position' => ($studio->contacts->max('position') ?? -1) + 1,
                ]);
            } else {
                if (! $contact) {
                    throw ValidationException::withMessages([
                        'action' => 'No matching contact was found. Add the submitted person as a new contact instead.',
                    ]);
                }

                $fields = collect($data['fields'] ?? []);
                $contact->update([
                    'name' => $fields->contains('name') ? $booking->contact_name : $contact->name,
                    'role' => $fields->contains('role') ? ($data['role'] ?? null) : $contact->role,
                    'emails' => $fields->contains('email') ? [mb_strtolower($booking->contact_email)] : $contact->emails,
                    'phone' => $fields->contains('phone') ? $booking->contact_phone : $contact->phone,
                ]);
            }

            if (in_array('studio_name', $data['fields'] ?? [], true)) {
                $studio->update(['name' => $booking->studio_name]);
            }

            $this->syncLegacyPrimaryContact($studio->fresh('contacts'));

            return $contact->refresh();
        });
    }

    private function syncLegacyPrimaryContact(Studio $studio): void
    {
        $primary = $studio->contacts->first();
        $studio->update([
            'contact_name' => $primary?->name,
            'contact_email' => $primary?->emailAddresses()[0] ?? null,
            'contact_phone' => $primary?->phone,
        ]);
    }
}
