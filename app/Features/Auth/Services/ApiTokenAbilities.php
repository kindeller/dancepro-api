<?php

namespace App\Features\Auth\Services;

use App\Features\Auth\Support\TokenAbility;
use App\Models\User;

class ApiTokenAbilities
{
    /**
     * @return list<string>
     */
    public function for(User $user): array
    {
        $abilities = [TokenAbility::AccountRead->value];

        if ($user->canAccessAdmin()) {
            $abilities[] = TokenAbility::CompetitionObjectsRead->value;
            $abilities[] = TokenAbility::DownloadLinksManage->value;
        }

        return $abilities;
    }
}
