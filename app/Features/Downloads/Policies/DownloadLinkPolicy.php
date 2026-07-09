<?php

namespace App\Features\Downloads\Policies;

use App\Features\Downloads\Models\DownloadLink;
use App\Models\User;

class DownloadLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, DownloadLink $downloadLink): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function revoke(User $user, DownloadLink $downloadLink): bool
    {
        return $user->is_active;
    }
}
