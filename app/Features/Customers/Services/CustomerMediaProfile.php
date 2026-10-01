<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Models\CustomerStudioRequest;
use App\Features\Families\Services\FamilyAccess;
use App\Features\Families\Services\FamilyGalleryAccess;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioContact;
use App\Models\User;

class CustomerMediaProfile
{
    public function __construct(private readonly CustomerConcertGallery $concertGallery, private readonly StudioCommunicationHistory $communicationHistory) {}

    public function forUser(User $user): array
    {
        abort_unless($user->canAccessCustomer(), 403);
        $dancers = app(FamilyAccess::class)->dancers($user);
        $studios = app(FamilyGalleryAccess::class)->studios($user)->whereNotNull('access_verified_at')->whereHas('studio', fn ($query) => $query->where('status', 'active'))
            ->with('studio')->get()->pluck('studio')->unique('id')->sortBy('name')->values();

        return compact('dancers', 'studios');
    }

    public function studioPhotos(User $user, Studio $studio): array
    {
        abort_unless($user->canAccessCustomer() && $studio->status->value === 'active'
            && app(FamilyGalleryAccess::class)->studios($user)->where('studio_id', $studio->id)->whereNotNull('access_verified_at')->exists(), 403);
        $concerts = $studio->concerts()->whereIn('status', ['draft', 'published'])->with('bookingItem')->orderByDesc('event_date')->get()
            ->filter(fn ($concert) => $concert->isPhotoGalleryAvailable());

        $nativeAlbums = $concerts->mapWithKeys(fn ($concert) => [$concert->uuid => $this->concertGallery->hasNativeAlbums($concert)])->all();

        $covers = $concerts->mapWithKeys(fn ($concert) => [$concert->uuid => $this->concertGallery->parentCoverAsset($concert)])->all();

        return compact('studio', 'concerts', 'nativeAlbums', 'covers');
    }

    public function editor(User $user): array
    {
        $user->refresh();

        $links = app(FamilyGalleryAccess::class)->studios($user)->with('studio.contacts')->get();

        return $this->forUser($user) + [
            'account' => $user, 'profile' => $user->customerProfile,
            'profilePhone' => $user->customerProfile?->phone ?: $user->crewProfile?->phone
                ?: StudioContact::where('user_id', $user->id)->whereNotNull('phone')->value('phone'),
            'emailPreferences' => app(CustomerCommunicationPreferences::class)->formDefaults($user),
            'registeredStudios' => $links->pluck('studio')->filter()->unique('id')->sortBy('name'),
            'ownStudioIds' => $links->where('user_id', $user->id)->pluck('studio_id')->all(),
            'familyStudioIds' => $links->where('user_id', '!=', $user->id)->pluck('studio_id')->unique()->values()->all(),
            'verifiedStudioIds' => $links->whereNotNull('access_verified_at')->pluck('studio_id')->all(),
            'communicationStudioIds' => $links->filter(fn (CustomerStudio $link): bool => $link->studio !== null && $this->communicationHistory->canView($user, $link->studio))->pluck('studio_id')->all(),
            'studioRequests' => CustomerStudioRequest::where('user_id', $user->id)->whereIn('status', ['pending', 'declined'])->latest()->get(),
            'selectedStudio' => Studio::where('status', 'active')->where('uuid', old('studio_uuid'))->first(),
        ];
    }
}
