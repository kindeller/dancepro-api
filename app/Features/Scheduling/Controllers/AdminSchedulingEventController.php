<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Operations\Services\ChecklistProgressForAssignments;
use App\Features\Scheduling\Actions\BulkUpdateSchedulingEvents;
use App\Features\Scheduling\Actions\PublishRoster;
use App\Features\Scheduling\Actions\SaveSchedulingEvent;
use App\Features\Scheduling\Actions\UpdateAdminAvailabilityResponse;
use App\Features\Scheduling\Actions\UpdateAssignmentEquipment;
use App\Features\Scheduling\Actions\UpdateAvailabilityRound;
use App\Features\Scheduling\Actions\UpdateEventCrewAssignment;
use App\Features\Scheduling\Actions\UpdateSchedulingShiftTimes;
use App\Features\Scheduling\Actions\UpdateTeamLeader;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Requests\BulkUpdateSchedulingEventsRequest;
use App\Features\Scheduling\Requests\SaveSchedulingEventRequest;
use App\Features\Scheduling\Requests\UpdateAdminAvailabilityResponseRequest;
use App\Features\Scheduling\Requests\UpdateAssignmentEquipmentRequest;
use App\Features\Scheduling\Requests\UpdateAvailabilityRoundRequest;
use App\Features\Scheduling\Requests\UpdateEventCrewAssignmentRequest;
use App\Features\Scheduling\Requests\UpdateSchedulingShiftTimesRequest;
use App\Features\Scheduling\Requests\UpdateTeamLeaderRequest;
use App\Features\Scheduling\Services\EquipmentScheduleDetails;
use App\Features\Scheduling\Services\PaymentPreviewCalculator;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Features\Scheduling\Support\ShiftPeriod;
use App\Features\Venues\Models\Venue;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminSchedulingEventController extends Controller
{
    public function index(Request $request, EquipmentScheduleDetails $equipmentScheduleDetails): View
    {
        Gate::authorize('manageScheduling');

        $selectedType = $request->filled('type') ? EventTypeDefinition::query()->where('uuid', $request->string('type'))->first() : null;

        $shifts = SchedulingShift::query()
            ->with(['schedulingEvent.venue', 'schedulingEvent.roleRequirements.crewRole', 'schedulingEvent.concertBookingItem.booking', 'assignments.crewProfile', 'assignments.equipmentResponsibilities', 'availabilityResponses'])
            ->whereHas('schedulingEvent', function ($query) use ($request, $selectedType): void {
                $query->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
                    ->when($selectedType, fn ($query) => $query->where('event_type_definition_id', $selectedType->id))
                    ->when($request->filled('status'), fn ($query) => $query->where('availability_status', $request->string('status')));
            })
            ->orderBy('shift_date')
            ->orderBy('period')
            ->paginate(50)
            ->withQueryString();

        $crew = CrewProfile::query()->with(['user', 'roles'])->whereHas('user', fn ($query) => $query->where('is_active', true))->orderBy('preferred_name')->get();
        $assignmentConflicts = $this->assignmentConflicts();
        $eventReadiness = SchedulingEvent::query()
            ->whereIn('id', $shifts->getCollection()->pluck('scheduling_event_id')->unique())
            ->with(['roleRequirements', 'shifts.assignments'])
            ->get()
            ->mapWithKeys(fn (SchedulingEvent $event): array => [$event->uuid => $event->rosterIsReady()]);

        return view('admin.scheduling-events.index', compact('shifts', 'crew', 'assignmentConflicts', 'eventReadiness') + [
            'filters' => $request->only(['search', 'type', 'status']),
            'filterEventTypes' => EventTypeDefinition::query()->where('is_active', true)->orderBy('name')->get(),
            'equipmentJourneyDetails' => $equipmentScheduleDetails->execute(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('manageScheduling');

        if ($request->string('type')->toString() !== SchedulingEventType::Competition->value) {
            return view('admin.scheduling-events.choose-type', [
                'eventTypes' => EventTypeDefinition::query()->where('is_active', true)->orderBy('name')->get(),
            ]);
        }

        return view('admin.scheduling-events.create', $this->formData() + [
            'selectedEventType' => EventTypeDefinition::query()
                ->where('uuid', $request->string('event_type')->toString())
                ->where('system_category', SchedulingEventType::Competition->value)
                ->where('is_active', true)
                ->firstOr(fn () => EventTypeDefinition::query()->where('code', 'competition')->firstOrFail()),
        ]);
    }

    public function store(SaveSchedulingEventRequest $request, SaveSchedulingEvent $saveEvent): RedirectResponse
    {
        $event = $saveEvent->execute($request->validated());

        return redirect()->route('admin.scheduling-events.show', $event)->with('status', 'Event created.');
    }

    public function show(SchedulingEvent $schedulingEvent, ChecklistProgressForAssignments $checklistProgress, PaymentPreviewCalculator $paymentPreview): View
    {
        Gate::authorize('manageScheduling');

        $schedulingEvent->load(['venue', 'roleRequirements.crewRole', 'messages.author', 'messages.reads', 'shifts.availabilityResponses.crewProfile.user', 'shifts.assignments.crewProfile', 'shifts.assignments.role', 'shifts.assignments.checklistCompletions', 'shifts.assignments.timeEntry.audits', 'shifts.assignments.allowances', 'shifts.assignments.shift.schedulingEvent']);
        $assignments = $schedulingEvent->shifts->flatMap->assignments;

        return view('admin.scheduling-events.show', [
            'event' => $schedulingEvent,
            'activeCrewCount' => CrewProfile::query()->whereHas('user', fn ($query) => $query->where('is_active', true))->count(),
            'checklistProgress' => $checklistProgress->execute($assignments),
            'assignedCrewUserIds' => $schedulingEvent->shifts->flatMap->assignments->pluck('crewProfile.user_id')->filter()->unique(),
            'paymentPreviews' => $assignments->mapWithKeys(fn (SchedulingShiftAssignment $assignment): array => [$assignment->id => $paymentPreview->execute($assignment)]),
        ]);
    }

    public function edit(SchedulingEvent $schedulingEvent): View
    {
        Gate::authorize('manageScheduling');
        abort_unless($schedulingEvent->event_type === SchedulingEventType::Competition, 404);
        $schedulingEvent->load(['shifts', 'roleRequirements.crewRole']);

        return view('admin.scheduling-events.edit', ['event' => $schedulingEvent] + $this->formData());
    }

    public function update(SaveSchedulingEventRequest $request, SchedulingEvent $schedulingEvent, SaveSchedulingEvent $saveEvent): RedirectResponse
    {
        $saveEvent->execute($request->validated(), $schedulingEvent);

        return redirect()->route('admin.scheduling-events.show', $schedulingEvent)->with('status', 'Event updated.');
    }

    public function updateShiftTimes(UpdateSchedulingShiftTimesRequest $request, SchedulingEvent $schedulingEvent, SchedulingShift $schedulingShift, UpdateSchedulingShiftTimes $updateTimes): RedirectResponse
    {
        abort_unless($schedulingShift->scheduling_event_id === $schedulingEvent->id, 404);
        $updateTimes->execute($schedulingShift, $request->string('start_time')->toString(), $request->string('finish_time')->toString());

        return back()->with('status', 'Shift times updated.');
    }

    public function updateAvailability(UpdateAvailabilityRoundRequest $request, SchedulingEvent $schedulingEvent, UpdateAvailabilityRound $updateAvailability): RedirectResponse
    {
        $updateAvailability->execute($schedulingEvent, $request->validated());

        return back()->with('status', $request->input('availability_status') === 'open' ? 'Availability opened.' : 'Availability closed.');
    }

    public function updateCrewAvailability(UpdateAdminAvailabilityResponseRequest $request, SchedulingShift $schedulingShift, CrewProfile $crewProfile, UpdateAdminAvailabilityResponse $updateResponse): JsonResponse|RedirectResponse
    {
        $updateResponse->execute($schedulingShift, $crewProfile, $request->string('status')->toString());

        return $request->expectsJson() ? response()->json(['success' => true]) : back()->with('status', 'Availability updated.');
    }

    public function updateCrewAssignment(UpdateEventCrewAssignmentRequest $request, SchedulingShift $schedulingShift, CrewRole $crewRole, UpdateEventCrewAssignment $updateAssignment): JsonResponse|RedirectResponse
    {
        $crewProfile = $request->filled('crew_profile_uuid')
            ? CrewProfile::query()->where('uuid', $request->string('crew_profile_uuid'))->firstOrFail()
            : null;
        $updateAssignment->execute($schedulingShift, $crewRole, $crewProfile);

        return $request->expectsJson() ? response()->json(['success' => true]) : back()->with('status', 'Crew assignment updated.');
    }

    public function updateTeamLeader(UpdateTeamLeaderRequest $request, SchedulingShift $schedulingShift, CrewProfile $crewProfile, UpdateTeamLeader $updateTeamLeader): JsonResponse|RedirectResponse
    {
        $updateTeamLeader->execute($schedulingShift, $crewProfile, $request->boolean('is_team_leader'));

        return $request->expectsJson() ? response()->json(['success' => true]) : back()->with('status', 'Team Leader updated.');
    }

    public function updateAssignmentEquipment(UpdateAssignmentEquipmentRequest $request, SchedulingShiftAssignment $assignment, UpdateAssignmentEquipment $updateEquipment): JsonResponse|RedirectResponse
    {
        $updateEquipment->execute($assignment, $request->string('item_code')->toString(), $request->boolean('is_bringing'), $request->boolean('is_taking'), $request->string('other_notes')->toString() ?: null);

        return $request->expectsJson() ? response()->json(['success' => true]) : back()->with('status', 'Equipment responsibility updated.');
    }

    public function publishRoster(SchedulingEvent $schedulingEvent, PublishRoster $publishRoster): RedirectResponse
    {
        Gate::authorize('manageScheduling');
        $publishRoster->execute($schedulingEvent);

        return back()->with('status', 'Roster published. Assigned crew have been notified and must acknowledge their shifts.');
    }

    public function bulkUpdate(BulkUpdateSchedulingEventsRequest $request, BulkUpdateSchedulingEvents $bulkUpdate): RedirectResponse
    {
        $eventIds = $request->validated('event_ids') ?? [];
        $bookingItemIds = $request->validated('booking_item_ids') ?? [];
        if ($eventIds === [] && $bookingItemIds === []) {
            return back()->withErrors(['events' => 'Select at least one event.']);
        }
        /** @var User $staff */
        $staff = $request->user();
        $events = SchedulingEvent::query()->whereIn('uuid', $eventIds)->get();
        $bookingItems = ConcertBookingItem::query()->with('booking')->whereIn('uuid', $bookingItemIds)->get();
        $count = $bulkUpdate->execute($events, $bookingItems, $request->string('action')->toString(), $request->validated('deadline_date'), $staff);

        return back()->with('status', "{$count} event(s) updated.");
    }

    private function formData(): array
    {
        return [
            'venues' => Venue::query()->orderBy('name')->get(),
            'competitionContacts' => CompetitionContact::query()->where('is_active', true)->orderBy('name')->get(),
            'eventTypes' => SchedulingEventType::cases(),
            'periods' => ShiftPeriod::cases(),
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
