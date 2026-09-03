<?php

namespace App\Features\Crew\Services;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use App\Models\User;

class CrewMobileDirectory
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        return [
            'crew' => CrewProfile::query()->where('user_id', '!=', $user->id)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->with('user')->orderBy('preferred_name')->get()->map(fn (CrewProfile $profile): array => [
                    'id' => $profile->uuid,
                    'name' => $profile->preferred_name ?: $profile->user->name,
                    'phone' => $profile->phone,
                    'profile_photo_url' => null,
                ])->values(),
            'studios' => Studio::query()->where('status', StudioStatus::Active)
                ->with('contacts')->orderBy('name')->get()->map(fn (Studio $studio): array => [
                    'id' => $studio->uuid,
                    'code' => $studio->code,
                    'name' => $studio->name,
                    'logo_url' => $studio->logoUrl(),
                    'contacts' => $studio->contacts->map(fn ($contact): array => [
                        'name' => $contact->name,
                        'role' => $contact->role,
                        'emails' => $contact->emailAddresses(),
                        'phone' => $contact->phone,
                    ])->values(),
                ])->values(),
            'competitions' => CompetitionContact::query()->where('is_active', true)
                ->with('staff')->orderBy('name')->get()->map(fn (CompetitionContact $competition): array => [
                    'id' => $competition->uuid,
                    'code' => $competition->code,
                    'name' => $competition->name,
                    'logo_url' => $competition->logoUrl(),
                    'contacts' => $competition->staff->map(fn ($contact): array => [
                        'name' => $contact->name,
                        'role' => $contact->role,
                        'emails' => $contact->emailAddresses(),
                        'phone' => $contact->phone,
                    ])->values(),
                ])->values(),
        ];
    }
}
