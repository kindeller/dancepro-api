<?php

namespace App\Features\Scheduling\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrewAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $event = $this->shift->schedulingEvent;
        $eventType = $event->event_type->value;
        $openCoverRequest = $this->relationLoaded('coverRequests')
            ? $this->coverRequests->firstWhere('status', 'open')
            : null;
        $cancelled = $event->cancelled_at !== null;
        $timeEntry = $this->timeEntry;
        $locked = $timeEntry?->locked_at !== null;
        $hasClockIn = $timeEntry?->actual_clock_in_at !== null;
        $hasFinish = $timeEntry?->actual_finish_at !== null;
        $clockOutIsAvailable = $hasClockIn
            || ($this->shift->posted_arrival_at?->copy()->addHours(2)->isPast() ?? false);
        $canFinishTeam = $this->is_team_leader
            && $event->event_type->value === 'competition'
            && $this->shift->relationLoaded('assignments')
            && $this->shift->assignments->where('status', 'published')->contains(
                fn ($assignment): bool => $assignment->timeEntry?->actual_finish_at === null,
            );
        $canRequestCover = ! $locked
            && ! $hasClockIn
            && ! $this->shift->shift_date->lt(today())
            && (! $this->relationLoaded('coverRequests') || $this->coverRequests->where('status', 'open')->isEmpty());
        $allowedActions = $cancelled ? collect() : collect([
            'acknowledge' => $this->acknowledgement_status !== 'acknowledged',
            'clock_in' => ! $locked && ! $hasClockIn,
            'clock_out' => ! $locked && ! $hasFinish && $clockOutIsAvailable,
            'edit_time' => ! $locked,
            'finish_team' => ! $locked && $canFinishTeam,
            'request_cover' => $canRequestCover,
        ])->filter()->keys()->values();

        return [
            'id' => $this->uuid,
            'event_name' => $event->name,
            'event_type' => $eventType,
            'event_logo_url' => $event->crewLogoUrl(),
            'event_code' => $event->eventCode(),
            'event_item_title' => $event->concertBookingItem?->title,
            'role' => $this->role->name,
            'is_team_leader' => $this->is_team_leader,
            'shift_period' => $this->shift->period?->value,
            'shift_code' => $this->shift->displayCode(),
            'shift_date' => $this->shift->shift_date->toDateString(),
            'arrival_at' => $this->shift->posted_arrival_at?->toIso8601String(),
            'starts_at' => $this->shift->starts_at?->toIso8601String(),
            'estimated_finish_at' => $this->shift->estimated_finish_at?->toIso8601String(),
            'status' => $this->status,
            'cancelled' => $cancelled,
            'cancelled_at' => $event->cancelled_at?->toIso8601String(),
            'acknowledged' => $this->acknowledgement_status === 'acknowledged',
            'cover_request' => $openCoverRequest ? [
                'id' => $openCoverRequest->uuid,
                'status' => $openCoverRequest->status,
            ] : null,
            'time' => [
                'actual_clock_in_at' => $timeEntry?->actual_clock_in_at?->toIso8601String(),
                'actual_finish_at' => $timeEntry?->actual_finish_at?->toIso8601String(),
                'optional_note' => $timeEntry?->optional_note,
                'locked' => $locked,
            ],
            'permissions' => [
                'can_clock_in' => ! $cancelled && ! $locked && ! $hasClockIn,
                'can_clock_out' => ! $cancelled && ! $locked && ! $hasFinish && $clockOutIsAvailable,
                'can_edit_time' => ! $cancelled && ! $locked,
                'can_finish_team' => ! $cancelled && ! $locked && $canFinishTeam,
            ],
            'allowed_actions' => $allowedActions,
            'version' => optional($this->published_at)->toIso8601String() ?? (string) $this->updated_at->getTimestamp(),
        ];
    }
}
