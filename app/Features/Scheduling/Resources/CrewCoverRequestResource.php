<?php

namespace App\Features\Scheduling\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrewCoverRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $request->user()->crewProfile;
        $recipient = $this->recipients->firstWhere('crew_profile_id', $profile->id);
        $assignment = $this->assignment;
        $event = $assignment->shift->schedulingEvent;

        return [
            'id' => $this->uuid,
            'direction' => $this->requested_by_crew_profile_id === $profile->id ? 'sent' : 'received',
            'event' => ['name' => $event->name, 'type' => $event->event_type->value],
            'shift' => [
                'date' => $assignment->shift->shift_date->toDateString(),
                'arrival_at' => $assignment->shift->posted_arrival_at?->toIso8601String(),
                'starts_at' => $assignment->shift->starts_at?->toIso8601String(),
                'estimated_finish_at' => $assignment->shift->estimated_finish_at?->toIso8601String(),
            ],
            'role' => $assignment->role->name,
            'requester' => ['id' => $this->requester->uuid, 'name' => $this->requester->preferred_name ?: $this->requester->user->name],
            'status' => $recipient?->status ?? $this->status,
            'request_status' => $this->status,
            'note' => $this->message,
            'requested_at' => $this->created_at->toIso8601String(),
            'responded_at' => $recipient?->responded_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'accepted_by' => $this->acceptedBy ? ['id' => $this->acceptedBy->uuid, 'name' => $this->acceptedBy->preferred_name ?: $this->acceptedBy->user->name] : null,
            'recipients' => $this->requested_by_crew_profile_id === $profile->id
                ? $this->recipients->map(fn ($item): array => [
                    'id' => $item->crewProfile->uuid,
                    'name' => $item->crewProfile->preferred_name ?: $item->crewProfile->user->name,
                    'status' => $item->status,
                    'responded_at' => $item->responded_at?->toIso8601String(),
                ])->values()
                : null,
        ];
    }
}
