<?php

namespace App\Features\Downloads\Policies;

use App\Features\Customers\Support\UserType;
use App\Features\Downloads\Models\DownloadLink;
use App\Models\User;

class DownloadLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, DownloadLink $downloadLink): bool
    {
        return $this->isStaff($user);
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function revoke(User $user, DownloadLink $downloadLink): bool
    {
        return $this->isStaff($user);
    }

    private function isStaff(User $user): bool
    {
        return $user->is_active
            && in_array($user->type, [UserType::Staff->value, UserType::Admin->value], true);
    }
}
