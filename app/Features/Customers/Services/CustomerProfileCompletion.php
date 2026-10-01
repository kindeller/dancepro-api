<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Models\CustomerStudioRequest;
use App\Features\Families\Services\FamilyAccess;
use App\Features\Families\Services\FamilyGalleryAccess;
use App\Models\User;

class CustomerProfileCompletion
{
    public function __construct(private readonly FamilyAccess $families, private readonly FamilyGalleryAccess $galleries) {}

    public function counts(User $user): array
    {
        $member = $this->families->membership($user);
        $hasDancer = $member
            ? $member->family->members()->where('is_dancer', true)->exists()
            : CustomerDancer::where('user_id', $user->id)->exists();
        $family = (int) ! $hasDancer;
        $hasPendingStudio = CustomerStudioRequest::where('user_id', $user->id)->where('status', 'pending')->exists();
        $studios = (int) (! $hasPendingStudio && ! $this->galleries->studios($user)->whereHas('studio')->exists());

        return ['family' => $family, 'studios' => $studios, 'total' => $family + $studios];
    }
}
