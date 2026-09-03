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
        $allowedActions = collect([
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
            'event_type' => $event->event_type->value,
            'role' => $this->role->name,
            'shift_date' => $this->shift->shift_date->toDateString(),
            'arrival_at' => $this->shift->posted_arrival_at?->toIso8601String(),
            'starts_at' => $this->shift->starts_at?->toIso8601String(),
            'estimated_finish_at' => $this->shift->estimated_finish_at?->toIso8601String(),
            'status' => $this->status,
            'acknowledged' => $this->acknowledgement_status === 'acknowledged',
            'time' => [
                'actual_clock_in_at' => $timeEntry?->actual_clock_in_at?->toIso8601String(),
                'actual_finish_at' => $timeEntry?->actual_finish_at?->toIso8601String(),
                'optional_note' => $timeEntry?->optional_note,
                'locked' => $locked,
            ],
            'permissions' => [
                'can_clock_in' => ! $locked && ! $hasClockIn,
                'can_clock_out' => ! $locked && ! $hasFinish && $clockOutIsAvailable,
                'can_edit_time' => ! $locked,
                'can_finish_team' => ! $locked && $canFinishTeam,
            ],
            'allowed_actions' => $allowedActions,
            'version' => optional($this->published_at)->toIso8601String() ?? (string) $this->updated_at->getTimestamp(),
        ];
    }
}
