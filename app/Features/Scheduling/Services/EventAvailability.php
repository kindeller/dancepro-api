<?php

namespace App\Features\Scheduling\Services;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Studios\Models\Studio;
use Illuminate\Support\Facades\DB;

class EventAvailability
{
    public function __construct(private readonly EquipmentScheduleDetails $equipmentScheduleDetails) {}

    public function page(array $filters = [], ?SchedulingEvent $onlyEvent = null): array
    {
        $studio = filled($filters['studio'] ?? null) ? Studio::where('uuid', $filters['studio'])->firstOrFail() : null;
        $selectedType = filled($filters['type'] ?? null) ? EventTypeDefinition::query()->where('uuid', $filters['type'])->first() : null;

        // Paginate pending requests and scheduled shifts together, without creating
        // crew-visible scheduling records before a booking has been approved.
        $shiftIndex = SchedulingShift::query()
            ->selectRaw("id, 'shift' as row_type, shift_date as row_date, TIME(starts_at) as row_time")
            ->whereHas('schedulingEvent', function ($query) use ($filters, $selectedType, $studio, $onlyEvent): void {
                $query->when(filled($filters['search'] ?? null), fn ($query) => $query->where(function ($query) use ($filters): void {
                    $search = '%'.$filters['search'].'%';
                    $query->where('name', 'like', $search)
                        ->orWhereHas('concertBookingItem', fn ($items) => $items->where('title', 'like', $search));
                }))
                    ->when($studio, fn ($query) => $query->where('studio_id', $studio->id))
                    ->when($onlyEvent, fn ($query) => $query->whereKey($onlyEvent->id))
                    ->when($selectedType, fn ($query) => $query->where('event_type_definition_id', $selectedType->id))
                    ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('availability_status', $filters['status']));
            });
        $pendingIndex = ConcertBookingItem::query()->awaitingApproval()
            ->selectRaw("id, 'pending' as row_type, event_date as row_date, TIME(starts_at) as row_time")
            ->when($onlyEvent || filled($filters['status'] ?? null) && $filters['status'] !== 'pending', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($studio, fn ($query) => $query->whereHas('booking', fn ($bookings) => $bookings->where('studio_id', $studio->id)))
            ->when($selectedType, fn ($query) => $query->where('event_type_definition_id', $selectedType->id))
            ->when(filled($filters['search'] ?? null), fn ($query) => $query->where(function ($query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where('title', 'like', $search)
                    ->orWhereHas('booking', fn ($bookings) => $bookings->where('studio_name', 'like', $search));
            }));
        $rows = DB::query()->fromSub($shiftIndex->unionAll($pendingIndex), 'availability_rows')
            ->orderBy('row_date')->orderBy('row_time')->orderBy('row_type')->orderBy('id')
            ->paginate(50)->withQueryString();
        $shifts = SchedulingShift::query()
            ->with(['schedulingEvent.eventTypeDefinition', 'schedulingEvent.competitionContact', 'schedulingEvent.studio', 'schedulingEvent.venue', 'schedulingEvent.roleRequirements.crewRole', 'schedulingEvent.concertBookingItem.booking', 'assignments.crewProfile', 'assignments.equipmentResponsibilities', 'availabilityResponses'])
            ->whereIn('id', $rows->getCollection()->where('row_type', 'shift')->pluck('id'))->get()->keyBy('id');
        $pendingItems = ConcertBookingItem::query()->with(['booking', 'venue', 'eventTypeDefinition'])
            ->whereIn('id', $rows->getCollection()->where('row_type', 'pending')->pluck('id'))->get()->keyBy('id');
        $rows->setCollection($rows->getCollection()->map(function ($row) use ($shifts, $pendingItems) {
            $row->item = ($row->row_type === 'pending' ? $pendingItems : $shifts)->get($row->id);

            return $row;
        })->filter(fn ($row) => $row->item !== null));

        $crew = CrewProfile::query()->with(['user', 'roles'])->whereHas('user', fn ($query) => $query->where('is_active', true))->orderBy('preferred_name')->get();
        $assignmentConflicts = $this->assignmentConflicts();
        $eventReadiness = SchedulingEvent::query()
            ->whereIn('id', $shifts->pluck('scheduling_event_id')->unique())
            ->with(['roleRequirements', 'shifts.assignments.role', 'shifts.assignments.crewProfile.user'])
            ->get()
            ->mapWithKeys(fn (SchedulingEvent $event): array => [$event->uuid => app(EventRosterStatus::class)->forEvent($event)]);

        return compact('rows', 'shifts', 'crew', 'assignmentConflicts', 'eventReadiness', 'filters') + [
            'availableRoles' => CrewRole::where('is_active', true)->orderBy('name')->get(),
            'filterStudios' => Studio::query()->orderBy('name')->get(['uuid', 'name']),
            'filterEventTypes' => EventTypeDefinition::query()->where('is_active', true)->orderBy('name')->get(),
            'equipmentJourneyDetails' => $this->equipmentScheduleDetails->execute(),
            'equipmentShiftCoverage' => $shifts->map(fn ($shift) => $this->equipmentScheduleDetails->shiftCoverage($shift)),
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
