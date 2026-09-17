<?php

namespace App\Features\Media\Policies;

use App\Features\Customers\Support\UserType;
use App\Features\Media\Models\MediaCollection;
use App\Models\User;

class MediaCollectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, MediaCollection $collection): bool
    {
        return $this->isStaff($user) && $collection->concert_id !== null;
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function update(User $user, MediaCollection $collection): bool
    {
        return $this->view($user, $collection);
    }

    private function isStaff(User $user): bool
    {
        return $user->is_active
            && in_array($user->type, [UserType::Staff->value, UserType::Admin->value], true);
    }
}
