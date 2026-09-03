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
            'version' => optional($this->published_at)->toIso8601String() ?? (string) $this->updated_at->getTimestamp(),
        ];
    }
}
