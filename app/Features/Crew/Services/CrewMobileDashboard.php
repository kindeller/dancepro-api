<?php

namespace App\Features\Crew\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Resources\CrewAssignmentResource;

class CrewMobileDashboard
{
    public function __construct(private readonly CrewNavigationIndicators $navigationIndicators) {}

    /** @return array<string, mixed> */
    public function for(CrewProfile $profile): array
    {
        $actionCounts = $this->navigationIndicators->actionCounts($profile->user);
        $nextAssignment = SchedulingShiftAssignment::query()
            ->where('crew_profile_id', $profile->id)
            ->where('status', 'published')
            ->whereHas('shift.schedulingEvent', fn ($query) => $query->whereNull('cancelled_at'))
            ->whereHas('shift', fn ($query) => $query->whereDate('shift_date', '>=', today()))
            ->with(['role', 'timeEntry', 'coverRequests', 'shift.schedulingEvent.eventTypeDefinition', 'shift.schedulingEvent.venue', 'shift.schedulingEvent.studio', 'shift.schedulingEvent.competitionContact', 'shift.schedulingEvent.concertBookingItem'])
            ->get()
            ->reject(fn ($assignment) => $assignment->hasFinishedForUpcomingCard())
            ->sortBy(fn ($assignment): string => $assignment->shift->shift_date->format('Y-m-d').' '.($assignment->shift->posted_arrival_at?->format('H:i:s') ?? '23:59:59'))
            ->first();

        return [
            'next_assignment' => $nextAssignment ? (new CrewAssignmentResource($nextAssignment))->resolve() : null,
            'unread_notifications' => CrewNotification::query()->where('user_id', $profile->user_id)->whereNull('read_at')->count(),
            'unread_chats' => $actionCounts['chats'],
            'pending_availability' => $actionCounts['availability'],
            'pending_invoices' => $actionCounts['invoices'],
            'pending_acknowledgements' => $actionCounts['acknowledgements'],
            'pending_cover_requests' => $actionCounts['cover_requests'],
            'pending_timesheets' => $actionCounts['timesheets'],
        ];
    }
}
