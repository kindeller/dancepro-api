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
            $abilities[] = TokenAbility::ConcertMediaRead->value;
            $abilities[] = TokenAbility::ProgrammeRead->value;
            $abilities[] = TokenAbility::ConcertMediaUpload->value;
            $abilities[] = TokenAbility::ConcertMediaUpdate->value;
        }

        if ($user->canAccessCrew()) {
            $abilities[] = TokenAbility::ProgrammeRead->value;
            $abilities[] = TokenAbility::ConcertMediaUpload->value;
        }

        return array_values(array_unique($abilities));
    }
}
