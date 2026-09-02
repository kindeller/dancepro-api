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
        $profile = $user->crewProfile;

        if (! $user->is_active || $profile === null) {
            return ['shifts' => 0, 'timesheets' => 0, 'chat' => 0];
        }

        $availabilityActions = SchedulingShift::query()
            ->whereHas('schedulingEvent', fn (Builder $query) => $query
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
            ->whereHas('shift', fn (Builder $query) => $query->whereDate('shift_date', '<=', today()))
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('timeEntry')
                    ->orWhereHas('timeEntry', fn (Builder $timeQuery) => $timeQuery
                        ->where('approval_status', '!=', 'externally_invoiced')
                        ->whereDoesntHave('invoiceLine'));
            })
            ->count();

        $pendingInvoices = CrewInvoice::query()
            ->where('crew_profile_id', $profile->id)
            ->where('status', 'pending_payment')
            ->count();

        $unreadChats = $this->chatInbox->conversations($user, 'unread')->sum('unread_count');

        return [
            'shifts' => $availabilityActions + $acknowledgementActions + $coverActions,
            'timesheets' => $pendingTimesheets + $pendingInvoices,
            'chat' => $unreadChats,
        ];
    }
}
