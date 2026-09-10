<?php

namespace App\Features\Scheduling\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Operations\Services\ChecklistItemsForAssignment;
use App\Features\Operations\Services\CrewMobileDocuments;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

class CrewMobileAssignments
{
    public function __construct(
        private readonly ChecklistItemsForAssignment $checklistItems,
        private readonly CrewMobileDocuments $documents,
    ) {}

    public function paginate(CrewProfile $profile, string $scope, int $limit): CursorPaginator
    {
        return $this->query($profile, in_array($scope, ['all', 'cancelled'], true))
            ->when($scope === 'cancelled', fn (Builder $query) => $query->whereHas('shift.schedulingEvent', fn (Builder $query) => $query->whereNotNull('cancelled_at')))
            ->when($scope === 'upcoming', fn (Builder $query) => $query->whereDate('scheduling_shifts.shift_date', '>=', today()))
            ->when($scope === 'past', fn (Builder $query) => $query->whereDate('scheduling_shifts.shift_date', '<', today()))
            ->orderBy('scheduling_shifts.shift_date')
            ->orderBy('scheduling_shift_assignments.id')
            ->cursorPaginate($limit);
    }

    public function findFor(CrewProfile $profile, SchedulingShiftAssignment $assignment): SchedulingShiftAssignment
    {
        $assignment = $this->query($profile, true)
            ->with(['equipmentResponsibilities', 'shift.assignments.crewProfile.user', 'shift.assignments.timeEntry'])
            ->whereKey($assignment->id)
            ->firstOrFail();

        $assignment->setRelation('mobileChecklist', $this->checklistItems->get($assignment));
        $assignment->setRelation('mobileDocuments', $this->documents->forAssignment($assignment));

        return $assignment;
    }

    public function findOwned(CrewProfile $profile, SchedulingShiftAssignment $assignment): SchedulingShiftAssignment
    {
        return $this->query($profile)
            ->whereKey($assignment->id)
            ->firstOrFail();
    }

    private function query(CrewProfile $profile, bool $includeCancelled = false): Builder
    {
        return SchedulingShiftAssignment::query()
            ->select('scheduling_shift_assignments.*')
            ->join('scheduling_shifts', 'scheduling_shifts.id', '=', 'scheduling_shift_assignments.scheduling_shift_id')
            ->where('scheduling_shift_assignments.crew_profile_id', $profile->id)
            ->where('scheduling_shift_assignments.status', 'published')
            ->when(! $includeCancelled, fn (Builder $query) => $query->whereHas('shift.schedulingEvent', fn (Builder $query) => $query->whereNull('cancelled_at')))
            ->with(['role', 'timeEntry', 'coverRequests', 'shift.schedulingEvent.eventTypeDefinition', 'shift.schedulingEvent.venue', 'shift.schedulingEvent.studio', 'shift.schedulingEvent.competitionContact', 'shift.schedulingEvent.concertBookingItem']);
    }
}
