<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use Illuminate\Validation\ValidationException;

class ClearAvailabilityResponse
{
    public function execute(CrewProfile $crewProfile, SchedulingShift $shift): void
    {
        $shift->loadMissing('schedulingEvent');

        if ($shift->assignments()->where('crew_profile_id', $crewProfile->id)->where('status', 'published')->exists()) {
            throw ValidationException::withMessages(['availability' => 'Your availability is locked because you are allocated to this shift.']);
        }

        if ($shift->schedulingEvent->availability_status !== AvailabilityRoundStatus::Open
            || $shift->schedulingEvent->availability_deadline?->isPast()) {
            throw ValidationException::withMessages(['availability' => 'This availability round is not open.']);
        }

        $response = $shift->availabilityResponses()
            ->where('crew_profile_id', $crewProfile->id)
            ->first();

        if ($response?->locked_at !== null) {
            throw ValidationException::withMessages(['availability' => 'This response is locked because the shift has been allocated.']);
        }

        $response?->delete();
    }
}
