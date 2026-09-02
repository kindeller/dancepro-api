<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\CrewAvailabilityResponse;
use App\Features\Scheduling\Models\SchedulingShift;

class UpdateAdminAvailabilityResponse
{
    public function execute(SchedulingShift $shift, CrewProfile $crewProfile, string $status): void
    {
        if ($status === 'unanswered') {
            CrewAvailabilityResponse::query()->whereBelongsTo($shift, 'shift')->whereBelongsTo($crewProfile)->delete();

            return;
        }

        CrewAvailabilityResponse::query()->updateOrCreate(
            ['scheduling_shift_id' => $shift->id, 'crew_profile_id' => $crewProfile->id],
            ['status' => $status, 'responded_at' => now(), 'locked_at' => null],
        );
    }
}
