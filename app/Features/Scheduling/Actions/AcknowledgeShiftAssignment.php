<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use Illuminate\Validation\ValidationException;

class AcknowledgeShiftAssignment
{
    public function execute(SchedulingShiftAssignment $assignment, CrewProfile $crewProfile): void
    {
        $assignment->loadMissing('shift.schedulingEvent');
        if ($assignment->shift->schedulingEvent->cancelled_at !== null) {
            throw ValidationException::withMessages(['assignment' => 'This shift has been cancelled.']);
        }

        if ($assignment->crew_profile_id !== $crewProfile->id || $assignment->status !== 'published') {
            throw ValidationException::withMessages(['assignment' => 'This shift is not allocated to you.']);
        }
        $assignment->update(['acknowledgement_status' => 'acknowledged', 'acknowledged_at' => now()]);
    }
}
