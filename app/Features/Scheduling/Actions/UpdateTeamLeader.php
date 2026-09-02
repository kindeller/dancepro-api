<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Scheduling\Support\SchedulingEventType;
use Illuminate\Validation\ValidationException;

class UpdateTeamLeader
{
    public function __construct(private readonly ResetAssignmentAcknowledgements $resetAcknowledgements) {}

    public function execute(SchedulingShift $shift, CrewProfile $crewProfile, bool $selected): void
    {
        $shift->loadMissing('schedulingEvent');
        if ($shift->schedulingEvent->event_type !== SchedulingEventType::Competition) {
            throw ValidationException::withMessages(['team_leader' => 'Team Leaders only apply to competition shifts.']);
        }

        $assignment = $shift->assignments()->where('crew_profile_id', $crewProfile->id)->first();
        if ($selected && $assignment === null) {
            throw ValidationException::withMessages(['team_leader' => 'Pencil this crew member into a technical role first.']);
        }

        $shift->assignments()->where('is_team_leader', true)->update(['is_team_leader' => false]);
        if ($assignment) {
            $assignment->update(['is_team_leader' => $selected]);
        }

        if ($shift->schedulingEvent->roster_status !== 'draft') {
            $this->resetAcknowledgements->execute($shift->schedulingEvent, 'The Team Leader responsibility changed');
        }
    }
}
