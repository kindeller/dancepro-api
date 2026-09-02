<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Operations\Models\ChecklistTemplate;
use App\Features\Operations\Services\ChecklistProgressForAssignments;
use App\Features\Operations\Support\OperationalRoleCodes;
use App\Features\Scheduling\Actions\AcknowledgeShiftAssignment;
use App\Features\Scheduling\Actions\RecordAvailabilityResponse;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Models\ShiftCoverRequest;
use App\Features\Scheduling\Models\ShiftCoverRequestRecipient;
use App\Features\Scheduling\Requests\RecordAvailabilityResponseRequest;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrewAvailabilityController extends Controller
{
    public function index(Request $request, ChecklistProgressForAssignments $checklistProgressForAssignments): View
    {
        abort_unless($request->user()?->is_active && $request->user()?->crewProfile !== null, 403);
        $crewProfile = $request->user()->crewProfile;

        $events = SchedulingEvent::query()
            ->where('availability_status', AvailabilityRoundStatus::Open)
            ->where('availability_deadline', '>=', now())
            ->with(['venue', 'shifts' => fn ($query) => $query->with([
                'availabilityResponses' => fn ($query) => $query->where('crew_profile_id', $crewProfile->id),
                'assignments' => fn ($query) => $query->where('crew_profile_id', $crewProfile->id)->where('status', 'published'),
            ])])
            ->orderBy('event_date')
            ->get();
        $events->each(function (SchedulingEvent $event): void {
            $event->setRelation('shifts', $event->shifts->filter(
                fn (SchedulingShift $shift): bool => $shift->assignments->isEmpty(),
            )->values());
        });
        $events = $events->filter(fn (SchedulingEvent $event): bool => $event->shifts->isNotEmpty())->values();

        $requestedFilter = $request->string('view')->toString();
        $assignments = SchedulingShiftAssignment::query()
            ->where('crew_profile_id', $crewProfile->id)
            ->where('status', 'published')
            ->with([
                'equipmentResponsibilities',
                'coverRequests',
                'role',
                'timeEntry',
                'checklistCompletions',
                'shift.assignments.timeEntry',
                'shift.assignments.crewProfile.user',
                'shift.schedulingEvent.venue',
                'shift.schedulingEvent.concertBookingItem.booking',
            ])
            ->get()
            ->sortBy(function (SchedulingShiftAssignment $assignment): string {
                $shiftDate = $assignment->shift->shift_date ?? $assignment->shift->schedulingEvent->event_date;

                return sprintf('%s %s', $shiftDate?->format('Y-m-d') ?? '9999-12-31', $assignment->shift->posted_arrival_at?->format('H:i:s') ?? '23:59:59');
            })
            ->values();
        $checklistProgress = $checklistProgressForAssignments->execute($assignments);
        $needsAcknowledgement = $assignments->where('acknowledgement_status', '!=', 'acknowledged')->count();
        $pendingAvailability = $events->sum(fn (SchedulingEvent $event): int => $event->shifts->filter(
            fn (SchedulingShift $shift): bool => $shift->availabilityResponses->isEmpty() && $shift->assignments->isEmpty(),
        )->count());
        $receivedCoverRequests = ShiftCoverRequestRecipient::query()->where('crew_profile_id', $crewProfile->id)
            ->with(['coverRequest.requester.user', 'coverRequest.acceptedBy.user', 'coverRequest.assignment.role', 'coverRequest.assignment.shift.schedulingEvent.venue'])
            ->latest()->get();
        $sentCoverRequests = ShiftCoverRequest::query()->where('requested_by_crew_profile_id', $crewProfile->id)
            ->with(['acceptedBy.user', 'recipients.crewProfile.user', 'assignment.role', 'assignment.shift.schedulingEvent.venue'])
            ->latest()->get();
        $pendingCoverCount = $receivedCoverRequests->filter(fn (ShiftCoverRequestRecipient $recipient): bool => $recipient->status === 'pending' && $recipient->coverRequest->status === 'open')->count();
        $filter = in_array($requestedFilter, ['availability', 'acknowledge', 'upcoming', 'cover', 'completed', 'calendar'], true)
            ? $requestedFilter
            : ($pendingAvailability > 0
                ? 'availability'
                : ($needsAcknowledgement > 0
                    ? 'acknowledge'
                    : ($pendingCoverCount > 0 ? 'cover' : 'upcoming')));
        $nextAssignment = $assignments->first(function (SchedulingShiftAssignment $assignment): bool {
            $shiftDate = $assignment->shift->shift_date ?? $assignment->shift->schedulingEvent->event_date;

            return $shiftDate !== null && ($shiftDate->isToday() || $shiftDate->isFuture());
        });
        $nextChecklistTemplates = collect();
        if ($nextAssignment !== null) {
            $roleCodes = OperationalRoleCodes::forAssignment($nextAssignment->role->code);
            $nextChecklistTemplates = ChecklistTemplate::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('event_type')->orWhere('event_type_definition_id', $nextAssignment->shift->schedulingEvent->event_type_definition_id)->orWhere(fn ($query) => $query->whereNull('event_type_definition_id')->where('event_type', $nextAssignment->shift->schedulingEvent->event_type->value)))
                ->where(fn ($query) => $query->whereNull('role_code')->orWhereIn('role_code', $roleCodes))
                ->with('items')
                ->get();
        }
        $visibleAssignments = $assignments->filter(function (SchedulingShiftAssignment $assignment) use ($filter): bool {
            $shiftDate = $assignment->shift->shift_date ?? $assignment->shift->schedulingEvent->event_date;
            if ($filter === 'acknowledge') {
                return $assignment->acknowledgement_status !== 'acknowledged';
            }

            if ($filter === 'completed') {
                return $shiftDate !== null && $shiftDate->isPast() && ! $shiftDate->isToday();
            }

            return $shiftDate !== null && ($shiftDate->isToday() || $shiftDate->isFuture());
        })->values();
        $studiosByName = Studio::query()->whereNotNull('logo_path')->get()->keyBy(fn (Studio $studio): string => mb_strtolower(trim($studio->name)));
        $displayEvents = $events->concat($assignments->map(fn (SchedulingShiftAssignment $assignment) => $assignment->shift->schedulingEvent))->unique('id');
        $eventLogoUrls = $displayEvents->mapWithKeys(function (SchedulingEvent $event) use ($studiosByName): array {
            $logoUrl = $event->logoUrl();
            if ($event->event_type === SchedulingEventType::Concert) {
                $logoUrl = $studiosByName->get(mb_strtolower(trim($event->name)))?->logoUrl();
            }

            return [$event->uuid => $logoUrl];
        });
        $notifications = CrewNotification::query()->where('user_id', $request->user()->id)->latest()->limit(10)->get();
        $assignmentsByDate = $assignments->groupBy(fn (SchedulingShiftAssignment $assignment): string => $assignment->shift->shift_date->toDateString());
        $assignmentDates = $assignments->map(fn (SchedulingShiftAssignment $assignment) => $assignment->shift->shift_date);
        $calendarStart = ($assignmentDates->first()?->copy() ?? today())->startOfMonth()->min(today()->startOfMonth());
        $calendarEnd = ($assignmentDates->last()?->copy() ?? today())->startOfMonth()->max(today()->startOfMonth());
        $calendarMonths = collect(CarbonPeriod::create($calendarStart, '1 month', $calendarEnd))->map(fn ($month) => $month->copy());

        return view('crew.availability.index', compact('events', 'crewProfile', 'assignments', 'visibleAssignments', 'nextAssignment', 'nextChecklistTemplates', 'notifications', 'filter', 'needsAcknowledgement', 'pendingAvailability', 'eventLogoUrls', 'receivedCoverRequests', 'sentCoverRequests', 'pendingCoverCount', 'assignmentsByDate', 'calendarMonths', 'checklistProgress'));
    }

    public function store(RecordAvailabilityResponseRequest $request, SchedulingShift $schedulingShift, RecordAvailabilityResponse $recordResponse): RedirectResponse
    {
        $recordResponse->execute($request->user()->crewProfile, $schedulingShift, $request->validated());

        return back()->with('status', 'Availability saved.');
    }

    public function acknowledge(Request $request, SchedulingShiftAssignment $assignment, AcknowledgeShiftAssignment $acknowledge): RedirectResponse
    {
        abort_unless($request->user()?->is_active && $request->user()?->crewProfile !== null, 403);
        $acknowledge->execute($assignment, $request->user()->crewProfile);

        return back()->with('status', 'Shift acknowledged. Thank you.');
    }

    public function markNotificationRead(Request $request, CrewNotification $crewNotification): JsonResponse
    {
        abort_unless($crewNotification->user_id === $request->user()?->id, 403);
        if ($crewNotification->read_at === null) {
            $crewNotification->update(['read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }
}
