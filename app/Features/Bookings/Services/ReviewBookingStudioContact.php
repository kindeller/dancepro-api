<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioContact;

class ReviewBookingStudioContact
{
    public function findStudio(ConcertBooking $booking, ?string $studioUuid = null): ?Studio
    {
        return Studio::query()
            ->with('contacts')
            ->when(
                $studioUuid,
                fn ($query) => $query->where('uuid', $studioUuid),
                fn ($query) => $query->whereRaw('lower(trim(name)) = ?', [$this->normaliseText($booking->studio_name)]),
            )
            ->first();
    }

    public function findContact(ConcertBooking $booking, Studio $studio): ?StudioContact
    {
        $email = $this->normaliseEmail($booking->contact_email);
        $name = $this->normaliseText($booking->contact_name);

        return $studio->contacts->first(
            fn (StudioContact $contact): bool => in_array($email, array_map($this->normaliseEmail(...), $contact->emailAddresses()), true),
        ) ?? $studio->contacts->first(
            fn (StudioContact $contact): bool => $this->normaliseText($contact->name) === $name,
        );
    }

    /** @return array{studio: ?Studio, contact: ?StudioContact, differences: array<string, array{label: string, submitted: string, stored: string}>} */
    public function compare(ConcertBooking $booking, ?string $studioUuid = null): array
    {
        $studio = $this->findStudio($booking, $studioUuid);
        if (! $studio) {
            return ['studio' => null, 'contact' => null, 'differences' => []];
        }

        $contact = $this->findContact($booking, $studio);
        $differences = [];
        $this->addDifference($differences, 'studio_name', 'Studio name', $booking->studio_name, $studio->name, $this->normaliseText(...));

        if (! $contact) {
            return ['studio' => $studio, 'contact' => null, 'differences' => $differences];
        }

        $this->addDifference($differences, 'name', 'Contact name', $booking->contact_name, $contact->name, $this->normaliseText(...));

        $submittedEmail = $this->normaliseEmail($booking->contact_email);
        if (! in_array($submittedEmail, array_map($this->normaliseEmail(...), $contact->emailAddresses()), true)) {
            $differences['email'] = [
                'label' => 'Email',
                'submitted' => $booking->contact_email,
                'stored' => $contact->emailString() ?: 'Not supplied',
            ];
        }

        $this->addDifference($differences, 'phone', 'Phone', $booking->contact_phone, $contact->phone ?: '', $this->normalisePhone(...));

        return ['studio' => $studio, 'contact' => $contact, 'differences' => $differences];
    }

    private function addDifference(array &$differences, string $key, string $label, string $submitted, string $stored, callable $normalise): void
    {
        if ($normalise($submitted) !== $normalise($stored)) {
            $differences[$key] = [
                'label' => $label,
                'submitted' => $submitted ?: 'Not supplied',
                'stored' => $stored ?: 'Not supplied',
            ];
        }
    }

    private function normaliseText(?string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $value)) ?? '');
    }

    private function normaliseEmail(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function normalisePhone(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
