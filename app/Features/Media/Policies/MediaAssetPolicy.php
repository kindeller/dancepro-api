<?php

namespace App\Features\Media\Policies;

use App\Features\Customers\Support\UserType;
use App\Features\Media\Models\MediaAsset;
use App\Models\User;

class MediaAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, MediaAsset $asset): bool
    {
        return $this->isStaff($user) && $asset->collection()->whereNotNull('concert_id')->exists();
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        return $this->view($user, $asset);
    }

    public function upload(User $user, MediaAsset $asset): bool
    {
        return $this->view($user, $asset);
    }

    private function isStaff(User $user): bool
    {
        return $user->is_active
            && in_array($user->type, [UserType::Staff->value, UserType::Admin->value], true);
    }
}
