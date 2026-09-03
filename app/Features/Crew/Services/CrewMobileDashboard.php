<?php

namespace App\Features\Crew\Services;

use App\Features\Chat\Services\CrewChatInbox;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Resources\CrewAssignmentResource;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use App\Features\Timesheets\Models\CrewInvoice;

class CrewMobileDashboard
{
    public function __construct(private readonly CrewChatInbox $chatInbox) {}

    /** @return array<string, mixed> */
    public function for(CrewProfile $profile): array
    {
        $nextAssignment = SchedulingShiftAssignment::query()
            ->where('crew_profile_id', $profile->id)
            ->where('status', 'published')
            ->whereHas('shift', fn ($query) => $query->whereDate('shift_date', '>=', today()))
            ->with(['role', 'shift.schedulingEvent.venue'])
            ->get()
            ->sortBy(fn ($assignment): string => $assignment->shift->shift_date->format('Y-m-d').' '.($assignment->shift->posted_arrival_at?->format('H:i:s') ?? '23:59:59'))
            ->first();

        $pendingAvailability = SchedulingEvent::query()
            ->where('availability_status', AvailabilityRoundStatus::Open)
            ->where('availability_deadline', '>=', now())
            ->whereHas('shifts', fn ($query) => $query
                ->whereDoesntHave('assignments', fn ($assignment) => $assignment->where('crew_profile_id', $profile->id)->where('status', 'published'))
                ->whereDoesntHave('availabilityResponses', fn ($response) => $response->where('crew_profile_id', $profile->id)))
            ->withCount(['shifts as pending_shift_count' => fn ($query) => $query
                ->whereDoesntHave('assignments', fn ($assignment) => $assignment->where('crew_profile_id', $profile->id)->where('status', 'published'))
                ->whereDoesntHave('availabilityResponses', fn ($response) => $response->where('crew_profile_id', $profile->id))])
            ->get()->sum('pending_shift_count');

        return [
            'next_assignment' => $nextAssignment ? (new CrewAssignmentResource($nextAssignment))->resolve() : null,
            'unread_notifications' => CrewNotification::query()->where('user_id', $profile->user_id)->whereNull('read_at')->count(),
            'unread_chats' => $this->chatInbox->conversations($profile->user, 'unread')->sum('unread_count'),
            'pending_availability' => $pendingAvailability,
            'pending_invoices' => CrewInvoice::query()->where('crew_profile_id', $profile->id)->where('status', 'pending_payment')->count(),
        ];
    }
}
