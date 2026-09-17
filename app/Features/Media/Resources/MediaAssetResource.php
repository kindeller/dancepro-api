<?php

namespace App\Features\Media\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'collection_uuid' => $this->whenLoaded('collection', fn () => $this->collection->uuid),
            'media_type' => $this->media_type,
            'display_name' => $this->display_name,
            'original_filename' => $this->original_filename,
            'status' => $this->status,
            'is_visible' => $this->is_visible,
            'sort_order' => $this->sort_order,
            'size_bytes' => $this->size_bytes,
            'duration_seconds' => $this->duration_seconds,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'expected_outputs' => data_get($this->metadata, 'ingest.expected_outputs', []),
            'verified_at' => $this->verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
