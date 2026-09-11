<?php

namespace App\Features\Crew\Services;

use App\Features\Chat\Services\CrewChatInbox;
use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Models\ShiftCoverRequestRecipient;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use App\Features\Timesheets\Models\CrewInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CrewNavigationIndicators
{
    public function __construct(private readonly CrewChatInbox $chatInbox) {}

    public function for(User $user): array
    {
        $counts = $this->actionCounts($user);

        return [
            'shifts' => $counts['availability'] + $counts['acknowledgements'] + $counts['cover_requests'],
            'timesheets' => $counts['timesheets'] + $counts['invoices'],
            'chat' => $counts['chats'],
        ];
    }

    /** @return array{availability: int, acknowledgements: int, cover_requests: int, timesheets: int, invoices: int, chats: int} */
    public function actionCounts(User $user): array
    {
        $profile = $user->crewProfile;

        if (! $user->is_active || $profile === null) {
            return ['availability' => 0, 'acknowledgements' => 0, 'cover_requests' => 0, 'timesheets' => 0, 'invoices' => 0, 'chats' => 0];
        }

        $availabilityActions = SchedulingShift::query()
            ->whereHas('schedulingEvent', fn (Builder $query) => $query
                ->whereNull('cancelled_at')
                ->where('availability_status', AvailabilityRoundStatus::Open)
                ->where('availability_deadline', '>=', now()))
            ->whereDoesntHave('availabilityResponses', fn (Builder $query) => $query->where('crew_profile_id', $profile->id))
            ->whereDoesntHave('assignments', fn (Builder $query) => $query
                ->where('crew_profile_id', $profile->id)
                ->where('status', 'published'))
            ->count();

        $acknowledgementActions = SchedulingShiftAssignment::query()
            ->where('crew_profile_id', $profile->id)
            ->where('status', 'published')
            ->whereHas('shift.schedulingEvent', fn (Builder $query) => $query->whereNull('cancelled_at'))
            ->where('acknowledgement_status', '!=', 'acknowledged')
            ->count();

        $coverActions = ShiftCoverRequestRecipient::query()
            ->where('crew_profile_id', $profile->id)
            ->where('status', 'pending')
            ->whereHas('coverRequest', fn (Builder $query) => $query->where('status', 'open'))
            ->count();

        $pendingTimesheets = SchedulingShiftAssignment::query()
            ->where('crew_profile_id', $profile->id)
            ->where('status', 'published')
            ->whereHas('shift.schedulingEvent', fn (Builder $query) => $query->whereNull('cancelled_at'))
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('timeEntry')
                    ->orWhereHas('timeEntry', fn (Builder $timeQuery) => $timeQuery
                        ->where('approval_status', '!=', 'externally_invoiced')
                        ->whereDoesntHave('invoiceLine'));
            })
            ->with(['timeEntry', 'shift.schedulingEvent.shifts'])
            ->get();
        $competitionGroups = $pendingTimesheets->groupBy('shift.scheduling_event_id');
        $pendingTimesheets = $pendingTimesheets->filter(function ($assignment) use ($competitionGroups): bool {
            $shift = $assignment->shift;
            $entry = $assignment->timeEntry;
            if ($entry?->locked_at !== null || ! $this->shiftHasFinished($shift)) {
                return false;
            }
            if (! $entry?->actual_clock_in_at || ! $entry?->actual_finish_at) {
                return true;
            }
            if ($shift->schedulingEvent->event_type->value !== 'competition') {
                return true;
            }

            return $shift->schedulingEvent->shifts->every(fn ($eventShift) => $this->shiftHasFinished($eventShift))
                && $competitionGroups[$shift->scheduling_event_id]->every(fn ($other) => $other->timeEntry?->actual_clock_in_at !== null && $other->timeEntry?->actual_finish_at !== null);
        })->count();

        $pendingInvoices = CrewInvoice::query()
            ->where('crew_profile_id', $profile->id)
            ->where('status', 'pending_payment')
            ->count();

        $unreadChats = $this->chatInbox->conversations($user, 'unread')->sum('unread_count');

        return [
            'availability' => $availabilityActions,
            'acknowledgements' => $acknowledgementActions,
            'cover_requests' => $coverActions,
            'timesheets' => $pendingTimesheets,
            'invoices' => $pendingInvoices,
            'chats' => $unreadChats,
        ];
    }

    private function shiftHasFinished(SchedulingShift $shift): bool
    {
        return ($shift->estimated_finish_at ?? $shift->shift_date->copy()->endOfDay())->lte(now());
    }
}
