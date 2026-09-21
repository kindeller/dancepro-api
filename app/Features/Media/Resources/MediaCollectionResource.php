<?php

namespace App\Features\Media\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'concert_uuid' => $this->whenLoaded('concert', fn () => $this->concert?->uuid),
            'name' => $this->name,
            'media_type' => $this->media_type,
            'catalogue_mode' => $this->catalogue_mode,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'sort_order' => $this->sort_order,
            'asset_count' => $this->whenCounted('assets'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
