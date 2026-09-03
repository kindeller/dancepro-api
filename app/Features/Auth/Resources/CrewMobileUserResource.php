<?php

namespace App\Features\Auth\Resources;

use App\Features\Crew\Actions\RefreshCrewOnboardingStatus;
use App\Features\Crew\Services\CrewOnboardingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrewMobileUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $this->crewProfile;
        app(RefreshCrewOnboardingStatus::class)->execute($profile);
        $onboarding = app(CrewOnboardingStatus::class)->for($profile);

        return [
            'id' => $profile->uuid,
            'name' => $profile->preferred_name ?: $this->name,
            'email' => $this->email,
            'capabilities' => ['crew'],
            'onboarding_complete' => $onboarding['complete'],
            'onboarding_missing' => $onboarding['missing'],
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
        ];
    }
}
