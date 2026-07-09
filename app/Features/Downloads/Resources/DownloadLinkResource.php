<?php

namespace App\Features\Downloads\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DownloadLinkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'key' => $this->storage_key,
            'disk' => $this->storage_disk,
            'original_filename' => $this->original_filename,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toISOString(),
            'first_opened_at' => $this->first_opened_at?->toISOString(),
            'last_opened_at' => $this->last_opened_at?->toISOString(),
            'download_count' => $this->download_count,
            'revoked_at' => $this->revoked_at?->toISOString(),
            'revoke_reason' => $this->revoke_reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
