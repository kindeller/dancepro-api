<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Studios\Models\Studio;
use App\Models\User;

class CustomerMediaProfile
{
    public function __construct(private readonly CustomerConcertGallery $concertGallery, private readonly StudioCommunicationHistory $communicationHistory) {}

    public function forUser(User $user): array
    {
        abort_unless($user->canAccessCustomer(), 403);
        $dancers = CustomerDancer::where('user_id', $user->id)->orderBy('name')->get();
        $studios = CustomerStudio::where('user_id', $user->id)->whereNotNull('access_verified_at')->whereHas('studio', fn ($query) => $query->where('status', 'active'))
            ->with('studio')->get()->pluck('studio')->sortBy('name')->values();

        return compact('dancers', 'studios');
    }

    public function studioPhotos(User $user, Studio $studio): array
    {
        abort_unless($user->canAccessCustomer() && $studio->status->value === 'active'
            && CustomerStudio::where('user_id', $user->id)->where('studio_id', $studio->id)->whereNotNull('access_verified_at')->exists(), 403);
        $concerts = $studio->concerts()->where('status', 'published')->with('bookingItem')->orderByDesc('event_date')->get()
            ->filter(fn ($concert) => $concert->isPubliclyAvailable());

        $nativeAlbums = $concerts->mapWithKeys(fn ($concert) => [$concert->uuid => $this->concertGallery->hasNativeAlbums($concert)])->all();

        return compact('studio', 'concerts', 'nativeAlbums');
    }

    public function editor(User $user): array
    {
        $user->refresh();

        $links = CustomerStudio::where('user_id', $user->id)->with('studio.contacts')->get();

        return $this->forUser($user) + [
            'account' => $user, 'profile' => $user->customerProfile,
            'registeredStudios' => $links->pluck('studio')->filter()->sortBy('name'),
            'verifiedStudioIds' => $links->whereNotNull('access_verified_at')->pluck('studio_id')->all(),
            'communicationStudioIds' => $links->filter(fn (CustomerStudio $link): bool => $link->studio !== null && $this->communicationHistory->canView($user, $link->studio))->pluck('studio_id')->all(),
            'availableStudios' => Studio::where('status', 'active')->orderBy('name')->get(),
        ];
    }
}
