<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use Illuminate\Validation\ValidationException;

class UpdateEventCrewAssignment
{
    public function execute(SchedulingShift $shift, CrewRole $role, ?CrewProfile $crewProfile): void
    {
        $shift->loadMissing('schedulingEvent');
        $event = $shift->schedulingEvent;
        if (! $event->roleRequirements()->where('crew_role_id', $role->id)->exists()) {
            throw ValidationException::withMessages(['crew_profile_uuid' => 'That role is not required for this event.']);
        }

        if ($crewProfile === null) {
            SchedulingShiftAssignment::query()->whereBelongsTo($shift, 'shift')->where('crew_role_id', $role->id)->delete();

            return;
        }

        if (! $crewProfile->roles()->whereKey($role->id)->wherePivot('status', 'approved')->exists()) {
            throw ValidationException::withMessages(['crew_profile_uuid' => 'That crew member is not approved for this role.']);
        }

        SchedulingShiftAssignment::query()->where('scheduling_shift_id', $shift->id)->where('crew_profile_id', $crewProfile->id)->where('crew_role_id', '!=', $role->id)->delete();
        $published = $event->roster_status !== 'draft';
        $assignment = SchedulingShiftAssignment::query()->updateOrCreate(
            ['scheduling_shift_id' => $shift->id, 'crew_role_id' => $role->id],
            ['crew_profile_id' => $crewProfile->id, 'status' => $published ? 'published' : 'draft', 'acknowledgement_status' => 'not_acknowledged', 'acknowledged_at' => null, 'published_at' => $published ? now() : null, 'notified_at' => $published ? now() : null],
        );
        if ($published) {
            $event->update(['roster_status' => 'changed']);
            $this->notify($assignment, 'Updated shift allocation');
        }
    }

    private function notify(SchedulingShiftAssignment $assignment, string $title): void
    {
        $assignment->loadMissing(['crewProfile.user', 'shift.schedulingEvent', 'role']);
        CrewNotification::query()->create([
            'user_id' => $assignment->crewProfile->user_id, 'type' => 'shift_allocation', 'title' => $title,
            'message' => "{$assignment->shift->schedulingEvent->name}: {$assignment->role->name} on {$assignment->shift->shift_date->format('D j M')}. Please acknowledge the shift.",
        ]);
        $assignment->shift->availabilityResponses()->where('crew_profile_id', $assignment->crew_profile_id)->update(['locked_at' => now()]);
    }
}
