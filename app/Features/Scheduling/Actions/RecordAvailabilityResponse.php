<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\CrewAvailabilityResponse;
use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use Illuminate\Validation\ValidationException;

class RecordAvailabilityResponse
{
    public function execute(CrewProfile $crewProfile, SchedulingShift $shift, array $data): CrewAvailabilityResponse
    {
        $shift->loadMissing('schedulingEvent');
        $event = $shift->schedulingEvent;

        if ($shift->assignments()->where('crew_profile_id', $crewProfile->id)->where('status', 'published')->exists()) {
            throw ValidationException::withMessages(['availability' => 'Your availability is locked because you are allocated to this shift.']);
        }

        if ($event->availability_status !== AvailabilityRoundStatus::Open || $event->availability_deadline?->isPast()) {
            throw ValidationException::withMessages(['availability' => 'This availability round is not open.']);
        }

        $response = CrewAvailabilityResponse::query()->firstOrNew([
            'scheduling_shift_id' => $shift->getKey(),
            'crew_profile_id' => $crewProfile->getKey(),
        ]);

        if ($response->locked_at !== null) {
            throw ValidationException::withMessages(['availability' => 'This response is locked because the shift has been allocated.']);
        }

        $response->fill($data + ['responded_at' => now()])->save();

        return $response;
    }
}
