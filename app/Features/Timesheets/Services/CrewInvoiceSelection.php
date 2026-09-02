<?php

namespace App\Features\Timesheets\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\AssignmentTimeEntry;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Services\PaymentPreviewCalculator;
use Illuminate\Validation\ValidationException;

class CrewInvoiceSelection
{
    public function __construct(private readonly PaymentPreviewCalculator $calculator) {}

    public function resolve(CrewProfile $crew, array $entryIds): array
    {
        $ids = collect($entryIds)->map(fn ($id) => (int) $id)->unique()->values();
        $entries = AssignmentTimeEntry::query()->whereKey($ids)
            ->whereHas('assignment', fn ($query) => $query->where('crew_profile_id', $crew->id))
            ->whereDoesntHave('invoiceLine')->where('approval_status', '!=', 'externally_invoiced')
            ->whereNotNull('actual_clock_in_at')->whereNotNull('actual_finish_at')
            ->with(['assignment.role', 'assignment.allowances', 'assignment.shift.schedulingEvent'])->get();

        if ($entries->count() !== $ids->count() || $entries->isEmpty()) {
            throw ValidationException::withMessages(['entry_ids' => 'Select valid pending timesheets from your own account.']);
        }

        $eventTypes = $entries->map(fn ($entry) => $entry->assignment->shift->schedulingEvent->event_type->value)->unique();
        if ($eventTypes->count() !== 1) {
            throw ValidationException::withMessages(['entry_ids' => 'Competition and concert timesheets cannot be combined.']);
        }

        $eventType = $eventTypes->first();
        $event = null;
        if ($eventType === 'competition') {
            $eventIds = $entries->pluck('assignment.shift.scheduling_event_id')->unique();
            if ($eventIds->count() !== 1) {
                throw ValidationException::withMessages(['entry_ids' => 'Each competition must have its own invoice.']);
            }
            $event = $entries->first()->assignment->shift->schedulingEvent;
            $pendingEventAssignments = SchedulingShiftAssignment::query()
                ->where('crew_profile_id', $crew->id)
                ->where('status', 'published')
                ->whereHas('shift', fn ($shift) => $shift->where('scheduling_event_id', $event->id))
                ->with('timeEntry.invoiceLine')
                ->get()
                ->filter(fn ($assignment) => ! $assignment->timeEntry?->invoiceLine && $assignment->timeEntry?->approval_status !== 'externally_invoiced');
            if ($pendingEventAssignments->contains(fn ($assignment) => ! $assignment->timeEntry?->actual_clock_in_at || ! $assignment->timeEntry?->actual_finish_at)) {
                throw ValidationException::withMessages(['entry_ids' => 'Confirm the start and finish times for every shift in this competition before invoicing.']);
            }
            $allEventEntryIds = AssignmentTimeEntry::query()
                ->whereHas('assignment', fn ($query) => $query->where('crew_profile_id', $crew->id)->whereHas('shift', fn ($shift) => $shift->where('scheduling_event_id', $event->id)))
                ->whereDoesntHave('invoiceLine')->where('approval_status', '!=', 'externally_invoiced')
                ->whereNotNull('actual_clock_in_at')->whereNotNull('actual_finish_at')->pluck('id');
            if ($allEventEntryIds->diff($ids)->isNotEmpty()) {
                throw ValidationException::withMessages(['entry_ids' => 'All pending timesheets for this competition must be included.']);
            }
        }

        $previews = $entries->mapWithKeys(fn ($entry) => [$entry->id => $this->calculator->execute($entry->assignment)]);
        if ($previews->contains(fn ($preview) => $preview['total'] === null)) {
            throw ValidationException::withMessages(['entry_ids' => 'A rate or essential time is missing. DancePro has been notified to correct it.']);
        }

        return compact('entries', 'previews', 'eventType', 'event');
    }
}
