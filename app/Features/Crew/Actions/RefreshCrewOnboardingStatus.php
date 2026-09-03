<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Services\CrewOnboardingStatus;

class RefreshCrewOnboardingStatus
{
    public function __construct(private readonly CrewOnboardingStatus $status) {}

    public function execute(CrewProfile $crewProfile): bool
    {
        $complete = $this->status->for($crewProfile)['complete'];
        $completedAt = $crewProfile->user->onboarding_completed_at;

        if ($complete && $completedAt === null) {
            $crewProfile->user->forceFill([
                'onboarding_completed_at' => now(),
            ])->save();
        }

        return $complete;
    }
}
