<?php

namespace App\Features\Exceptions\Services;

use App\Features\Operations\Models\EventMessage;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Models\ShiftCoverRequest;
use App\Features\Timesheets\Models\CrewInvoice;
use Illuminate\Support\Collection;

class AdminExceptionOverview
{
    private ?Collection $exceptions = null;

    public function all(): Collection
    {
        return $this->exceptions ??= collect()
            ->concat($this->shiftAndEventExceptions())
            ->concat($this->timekeepingExceptions())
            ->concat($this->paymentExceptions())
            ->concat($this->communicationExceptions())
            ->sortBy(fn (array $exception): string => sprintf(
                '%d-%s-%s',
                $exception['severity'] === 'action' ? 0 : 1,
                $exception['date']?->format('Y-m-d H:i:s') ?? '9999-12-31 23:59:59',
                $exception['title'],
            ))
            ->values();
    }

    private function shiftAndEventExceptions(): Collection
    {
        $events = SchedulingEvent::query()
            ->whereDate('event_date', '>=', today())
            ->with(['venue', 'roleRequirements.crewRole', 'shifts.roleRequirements.crewRole', 'shifts.assignments.crewProfile.user', 'shifts.assignments.role'])
            ->orderBy('event_date')
            ->get();

        $exceptions = collect();

        foreach ($events as $event) {
            $isApproaching = $event->event_date->lte(today()->addDays(14));

            foreach ($event->shifts as $shift) {
                $requirements = $shift->roleRequirements->isNotEmpty() ? $shift->roleRequirements : $event->roleRequirements;
                foreach ($requirements as $requirement) {
                    $assigned = $shift->assignments
                        ->where('crew_role_id', $requirement->crew_role_id)
                        ->whereNotNull('crew_profile_id')
                        ->count();
                    if ($assigned < $requirement->quantity && ($event->roster_status === 'published' || $isApproaching)) {
                        $missing = $requirement->quantity - $assigned;
                        $exceptions->push($this->item(
                            'shifts-events',
                            'action',
                            'Unfilled crew role',
                            $missing.' × '.$requirement->crewRole->name.' still '.($missing === 1 ? 'needs' : 'need').' assigning.',
                            $event,
                            $shift->shift_date,
                        ));
                    }
                }

                foreach ($shift->assignments->where('status', 'published')->where('acknowledgement_status', '!=', 'acknowledged') as $assignment) {
                    $exceptions->push($this->item(
                        'shifts-events',
                        'action',
                        'Shift not acknowledged',
                        ($assignment->crewProfile->preferred_name ?: $assignment->crewProfile->user->name).' has not acknowledged '.$assignment->role->name.'.',
                        $event,
                        $shift->shift_date,
                        $assignment->crewProfile->preferred_name ?: $assignment->crewProfile->user->name,
                    ));
                }
            }

            if ($isApproaching && $event->roster_status === 'published') {
                $missing = collect([
                    $event->venue_id ? null : 'venue',
                    $event->venue_id && ! $event->venue?->map_path ? 'venue map' : null,
                    ! $event->programme_path ? 'programme / run sheet' : null,
                    ! $event->crew_brief ? 'crew brief' : null,
                ])->filter()->values();

                if ($missing->isNotEmpty()) {
                    $exceptions->push($this->item(
                        'shifts-events',
                        'check',
                        'Event information incomplete',
                        'Missing '.str($missing->join(', ', ' and '))->finish('.'),
                        $event,
                        $event->event_date,
                    ));
                }
            }
        }

        ShiftCoverRequest::query()
            ->where('status', 'open')
            ->with(['requester.user', 'assignment.shift.schedulingEvent'])
            ->get()
            ->each(function (ShiftCoverRequest $request) use ($exceptions): void {
                $event = $request->assignment->shift->schedulingEvent;
                $exceptions->push($this->item(
                    'shifts-events',
                    'action',
                    'Open cover request',
                    ($request->requester->preferred_name ?: $request->requester->user->name).' still needs cover.',
                    $event,
                    $request->assignment->shift->shift_date,
                    $request->requester->preferred_name ?: $request->requester->user->name,
                ));
            });

        return $exceptions;
    }

    private function timekeepingExceptions(): Collection
    {
        $assignments = SchedulingShiftAssignment::query()
            ->where('status', 'published')
            ->whereHas('shift', fn ($query) => $query->whereDate('shift_date', '<=', today()))
            ->with(['crewProfile.user', 'role', 'timeEntry.audits', 'shift.schedulingEvent'])
            ->get();

        return $assignments->map(function (SchedulingShiftAssignment $assignment): ?array {
            $shift = $assignment->shift;
            $entry = $assignment->timeEntry;
            $event = $shift->schedulingEvent;
            $crewName = $assignment->crewProfile->preferred_name ?: $assignment->crewProfile->user->name;

            if ($shift->estimated_finish_at?->isFuture()) {
                return null;
            }

            if ($entry === null) {
                return $this->item('timekeeping', 'action', 'No time recorded', $crewName.' has no start or finish time for '.$assignment->role->name.'.', $event, $shift->shift_date, $crewName);
            }

            $flags = $entry->reviewFlags();
            if ($entry->actual_finish_at === null) {
                array_unshift($flags, 'Finish time missing');
            }
            if ($entry->actual_clock_in_at === null) {
                array_unshift($flags, 'Clock-in missing');
            }
            $flags = collect($flags)->unique()->values();

            if ($flags->isEmpty()) {
                return null;
            }

            return $this->item(
                'timekeeping',
                $entry->actual_finish_at === null ? 'action' : 'check',
                $entry->actual_finish_at === null ? 'Time record incomplete' : 'Time record needs checking',
                $crewName.': '.$flags->join('; ').'.',
                $event,
                $shift->shift_date,
                $crewName,
            );
        })->filter()->values();
    }

    private function paymentExceptions(): Collection
    {
        $exceptions = SchedulingShiftAssignment::query()
            ->where('status', 'published')
            ->whereHas('shift', fn ($query) => $query->whereDate('shift_date', '<=', today()->subDays(7)))
            ->whereHas('timeEntry', fn ($query) => $query
                ->whereNotNull('actual_finish_at')
                ->where('approval_status', '!=', 'externally_invoiced')
                ->whereDoesntHave('invoiceLine'))
            ->with(['crewProfile.user', 'role', 'shift.schedulingEvent'])
            ->get()
            ->map(function (SchedulingShiftAssignment $assignment): array {
                $crewName = $assignment->crewProfile->preferred_name ?: $assignment->crewProfile->user->name;

                return $this->item(
                    'payments',
                    'check',
                    'Completed work not invoiced',
                    $crewName.' completed this shift more than seven days ago.',
                    $assignment->shift->schedulingEvent,
                    $assignment->shift->shift_date,
                    $crewName,
                    route('admin.timesheets.index'),
                );
            });

        CrewInvoice::query()
            ->where('status', 'pending_payment')
            ->with(['crewProfile.user', 'schedulingEvent'])
            ->get()
            ->each(function (CrewInvoice $invoice) use ($exceptions): void {
                $crewName = $invoice->crewProfile->preferred_name ?: $invoice->crewProfile->user->name;
                $exceptions->push([
                    'category' => 'payments',
                    'severity' => $invoice->submitted_at?->lt(now()->subDays(14)) ? 'action' : 'check',
                    'title' => 'Invoice pending payment',
                    'detail' => 'Invoice '.($invoice->invoice_number ?: '#'.$invoice->id).' from '.$crewName.' is awaiting payment.',
                    'event' => $invoice->schedulingEvent?->name ?: 'Concerts',
                    'person' => $crewName,
                    'date' => $invoice->submitted_at ?? $invoice->created_at,
                    'url' => route('admin.timesheets.invoices.show', $invoice),
                    'action' => 'Open invoice',
                ]);
            });

        return $exceptions;
    }

    private function communicationExceptions(): Collection
    {
        return EventMessage::query()
            ->where('message_type', 'announcement')
            ->with(['reads', 'schedulingEvent.shifts.assignments.crewProfile.user'])
            ->get()
            ->map(function (EventMessage $message): ?array {
                $assignedUsers = $message->schedulingEvent->shifts
                    ->flatMap->assignments
                    ->where('status', 'published')
                    ->pluck('crewProfile.user')
                    ->filter()
                    ->unique('id');
                $readUserIds = $message->reads->whereNotNull('read_at')->pluck('user_id');
                $unread = $assignedUsers->whereNotIn('id', $readUserIds);

                if ($unread->isEmpty()) {
                    return null;
                }

                return $this->item(
                    'communication',
                    'check',
                    'Announcement not acknowledged',
                    $unread->count().' assigned '.str('crew member')->plural($unread->count()).' '.($unread->count() === 1 ? 'has' : 'have').' not acknowledged the announcement.',
                    $message->schedulingEvent,
                    $message->created_at,
                );
            })
            ->filter()
            ->values();
    }

    private function item(string $category, string $severity, string $title, string $detail, SchedulingEvent $event, mixed $date, ?string $person = null, ?string $url = null): array
    {
        return [
            'category' => $category,
            'severity' => $severity,
            'title' => $title,
            'detail' => $detail,
            'event' => $event->name,
            'person' => $person,
            'date' => $date,
            'url' => $url ?? route('admin.scheduling-events.show', $event),
            'action' => $url ? 'Open' : 'Open event',
        ];
    }
}
