<?php

namespace App\Features\Operations\Services;

use App\Features\Operations\Models\OperationalResource;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CrewMobileDocuments
{
    public function __construct(
        private readonly OperationsFileStorage $files,
        private readonly OperationalDocumentMetadata $metadata,
    ) {}

    public function list(?string $updatedSince): Collection
    {
        $since = filled($updatedSince) ? Carbon::parse($updatedSince) : null;

        return OperationalResource::query()->where('is_active', true)->whereNotNull('file_path')
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->orderBy('sort_order')->orderBy('title')->get()
            ->filter(fn (OperationalResource $resource): bool => $this->files->disk()->exists($resource->file_path))
            ->map(fn (OperationalResource $resource): array => $this->metadata($resource))->values();
    }

    public function authorised(OperationalResource $resource): OperationalResource
    {
        abort_unless($resource->is_active && filled($resource->file_path) && $this->files->disk()->exists($resource->file_path), 404);

        return $resource;
    }

    public function metadata(OperationalResource $resource): array
    {
        $resource = $this->authorised($resource);
        if (! filled($resource->file_mime_type) || $resource->file_size === null || ! filled($resource->file_checksum)) {
            $resource->fill($this->metadata->forPath($resource->file_path))->save();
        }

        return [
            'id' => $resource->uuid,
            'title' => $resource->title,
            'mime_type' => $resource->file_mime_type,
            'bytes' => $resource->file_size,
            'checksum' => $resource->file_checksum,
            'updated_at' => $resource->updated_at->toIso8601String(),
            'offline_allowed' => true,
        ];
    }
}
