<?php

namespace App\Features\Scheduling\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use Illuminate\Support\Collection;

class EligibleCoverCandidates
{
    /** @return Collection<int, CrewProfile> */
    public function execute(SchedulingShiftAssignment $assignment): Collection
    {
        $assignment->loadMissing(['role', 'shift.schedulingEvent']);
        $shift = $assignment->shift;

        return CrewProfile::query()
            ->with(['user', 'availabilityResponses' => fn ($query) => $query->where('scheduling_shift_id', $shift->id)])
            ->whereKeyNot($assignment->crew_profile_id)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->whereHas('roles', fn ($query) => $query->whereKey($assignment->crew_role_id)->where('crew_role_qualifications.status', 'approved'))
            ->when($assignment->is_team_leader, fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('code', 'team-leader')->where('crew_role_qualifications.status', 'approved')))
            ->whereDoesntHave('shiftAssignments', function ($query) use ($shift): void {
                $query->where('status', 'published')->whereHas('shift', function ($shifts) use ($shift): void {
                    $shifts->whereDate('shift_date', $shift->shift_date)
                        ->when(
                            $shift->starts_at && $shift->estimated_finish_at,
                            fn ($timed) => $timed->where('starts_at', '<', $shift->estimated_finish_at)->where('estimated_finish_at', '>', $shift->starts_at),
                        );
                });
            })
            ->orderBy('preferred_name')
            ->get();
    }

    public function contains(SchedulingShiftAssignment $assignment, CrewProfile $candidate): bool
    {
        return $this->execute($assignment)->contains('id', $candidate->id);
    }
}
