<?php

namespace App\Features\Scheduling\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrewCoverCandidateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $availability = $this->availabilityResponses->first()?->status?->value;

        return [
            'id' => $this->uuid,
            'name' => $this->preferred_name ?: $this->user->name,
            'availability_status' => $availability,
        ];
    }
}
