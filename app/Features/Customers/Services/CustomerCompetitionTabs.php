<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Families\Services\FamilyGalleryAccess;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;

class CustomerCompetitionTabs
{
    public function forEvent(User $user, SchedulingEvent $event): array
    {
        $studioLogoUrl = app(FamilyGalleryAccess::class)->studios($user)
            ->whereNotNull('access_verified_at')
            ->whereHas('studio', fn ($query) => $query->where('status', 'active')->whereNotNull('logo_path'))
            ->with('studio')->get()->pluck('studio')->sortBy('name')->first()?->logoUrl();
        $cartCount = CustomerMediaSelection::query()->where('user_id', $user->id)->where('in_cart', true)->count()
            + CustomerConcertPhotoSelection::query()->where('user_id', $user->id)->where('in_cart', true)->count();
        $favouriteCount = CustomerMediaSelection::query()->where('user_id', $user->id)->where('is_favourite', true)->count()
            + CustomerConcertPhotoSelection::query()->where('user_id', $user->id)->where('is_favourite', true)->count();

        return compact('studioLogoUrl', 'cartCount', 'favouriteCount');
    }
}
