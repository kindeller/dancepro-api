<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Support\CrewContractSignatureStatus;
use App\Features\Crew\Support\CrewContractStatus;

class RefreshCrewOnboardingStatus
{
    public function execute(CrewProfile $crewProfile): bool
    {
        $profileComplete = collect([
            $crewProfile->phone,
            $crewProfile->address_line_1,
            $crewProfile->suburb,
            $crewProfile->state,
            $crewProfile->postcode,
            $crewProfile->working_with_children_number,
            $crewProfile->working_with_children_expiry,
        ])->every(fn ($value): bool => filled($value));

        $activeContractIds = CrewContract::query()->where('status', CrewContractStatus::Active)->pluck('id');
        $signedContractIds = $crewProfile->contractSignatures()
            ->where('status', CrewContractSignatureStatus::Signed)
            ->whereIn('crew_contract_id', $activeContractIds)
            ->pluck('crew_contract_id');
        $complete = $profileComplete && $activeContractIds->diff($signedContractIds)->isEmpty();

        if ($complete && $crewProfile->user->onboarding_completed_at === null) {
            $crewProfile->user->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return $complete;
    }
}
