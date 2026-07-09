<?php

namespace App\Features\Downloads\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DownloadAccessResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accessed_at' => $this->accessed_at?->toISOString(),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'referrer' => $this->referrer,
            'was_successful' => $this->was_successful,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
