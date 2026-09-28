<?php

namespace App\Features\Scheduling\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Studios\Models\Studio;

class EventAvailability
{
    public function __construct(private readonly EquipmentScheduleDetails $equipmentScheduleDetails) {}

    public function page(array $filters = [], ?SchedulingEvent $onlyEvent = null): array
    {
        $studio = filled($filters['studio'] ?? null) ? Studio::where('uuid', $filters['studio'])->firstOrFail() : null;
        $selectedType = filled($filters['type'] ?? null) ? EventTypeDefinition::query()->where('uuid', $filters['type'])->first() : null;

        $shifts = SchedulingShift::query()
            ->with(['schedulingEvent.eventTypeDefinition', 'schedulingEvent.competitionContact', 'schedulingEvent.studio', 'schedulingEvent.venue', 'schedulingEvent.roleRequirements.crewRole', 'schedulingEvent.concertBookingItem.booking', 'assignments.crewProfile', 'assignments.equipmentResponsibilities', 'availabilityResponses'])
            ->whereHas('schedulingEvent', function ($query) use ($filters, $selectedType, $studio, $onlyEvent): void {
                $query->when(filled($filters['search'] ?? null), fn ($query) => $query->where('name', 'like', '%'.$filters['search'].'%'))
                    ->when($studio, fn ($query) => $query->where('studio_id', $studio->id))
                    ->when($onlyEvent, fn ($query) => $query->whereKey($onlyEvent->id))
                    ->when($selectedType, fn ($query) => $query->where('event_type_definition_id', $selectedType->id))
                    ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('availability_status', $filters['status']));
            })
            ->orderBy('shift_date')
            ->orderBy('period')
            ->paginate(50)
            ->withQueryString();

        $crew = CrewProfile::query()->with(['user', 'roles'])->whereHas('user', fn ($query) => $query->where('is_active', true))->orderBy('preferred_name')->get();
        $assignmentConflicts = $this->assignmentConflicts();
        $eventReadiness = SchedulingEvent::query()
            ->whereIn('id', $shifts->getCollection()->pluck('scheduling_event_id')->unique())
            ->with(['roleRequirements', 'shifts.assignments.role', 'shifts.assignments.crewProfile.user'])
            ->get()
            ->mapWithKeys(fn (SchedulingEvent $event): array => [$event->uuid => app(EventRosterStatus::class)->forEvent($event)]);

        return compact('shifts', 'crew', 'assignmentConflicts', 'eventReadiness', 'filters') + [
            'filterStudios' => Studio::query()->orderBy('name')->get(['uuid', 'name']),
            'filterEventTypes' => EventTypeDefinition::query()->where('is_active', true)->orderBy('name')->get(),
            'equipmentJourneyDetails' => $this->equipmentScheduleDetails->execute(),
        ];
    }

    private function assignmentConflicts(): array
    {
        $conflicts = [];
        SchedulingShiftAssignment::query()->with('shift')->get()->groupBy('crew_profile_id')->each(function ($assignments) use (&$conflicts): void {
            foreach ($assignments as $left) {
                foreach ($assignments->where('id', '!=', $left->id) as $right) {
                    if ($left->shift->starts_at && $left->shift->estimated_finish_at && $right->shift->starts_at && $right->shift->estimated_finish_at && $left->shift->starts_at->lt($right->shift->estimated_finish_at) && $right->shift->starts_at->lt($left->shift->estimated_finish_at)) {
                        $conflicts[$left->id] = true;
                        $conflicts[$right->id] = true;
                    }
                }
            }
        });

        return $conflicts;
    }
}
