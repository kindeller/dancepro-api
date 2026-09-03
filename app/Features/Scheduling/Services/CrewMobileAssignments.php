<?php

namespace App\Features\Scheduling\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

class CrewMobileAssignments
{
    public function paginate(CrewProfile $profile, string $scope, int $limit): CursorPaginator
    {
        return $this->query($profile)
            ->when($scope === 'upcoming', fn (Builder $query) => $query->whereDate('scheduling_shifts.shift_date', '>=', today()))
            ->when($scope === 'past', fn (Builder $query) => $query->whereDate('scheduling_shifts.shift_date', '<', today()))
            ->orderBy('scheduling_shifts.shift_date')
            ->orderBy('scheduling_shift_assignments.id')
            ->cursorPaginate($limit);
    }

    public function findFor(CrewProfile $profile, SchedulingShiftAssignment $assignment): SchedulingShiftAssignment
    {
        return $this->query($profile)
            ->with('shift.assignments.crewProfile.user')
            ->whereKey($assignment->id)
            ->firstOrFail();
    }

    private function query(CrewProfile $profile): Builder
    {
        return SchedulingShiftAssignment::query()
            ->select('scheduling_shift_assignments.*')
            ->join('scheduling_shifts', 'scheduling_shifts.id', '=', 'scheduling_shift_assignments.scheduling_shift_id')
            ->where('scheduling_shift_assignments.crew_profile_id', $profile->id)
            ->where('scheduling_shift_assignments.status', 'published')
            ->with(['role', 'shift.schedulingEvent.venue']);
    }
}
