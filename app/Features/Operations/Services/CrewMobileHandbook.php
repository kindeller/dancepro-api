<?php

namespace App\Features\Operations\Services;

use App\Features\Operations\Models\OperationalResource;
use App\Features\Operations\Support\ExternalResourceUrl;
use Illuminate\Support\Collection;

class CrewMobileHandbook
{
    public function __construct(
        private readonly OperationsFileStorage $files,
        private readonly CrewMobileDocuments $documents,
    ) {}

    public function list(): Collection
    {
        return OperationalResource::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('title')->get()
            ->map(fn (OperationalResource $resource): array => $this->resource($resource));
    }

    private function resource(OperationalResource $resource): array
    {
        $hasDocument = filled($resource->file_path) && $this->files->disk()->exists($resource->file_path);

        return [
            'id' => $resource->uuid,
            'section_number' => $resource->section_number,
            'type' => $resource->resource_type,
            'title' => $resource->title,
            'summary' => $resource->summary,
            'content' => $resource->content,
            'event_type' => $resource->event_type,
            'role_code' => $resource->role_code,
            'external_url' => ExternalResourceUrl::isAllowed($resource->external_url) ? $resource->external_url : null,
            'document' => $hasDocument ? $this->documents->metadata($resource) : null,
            'updated_at' => $resource->updated_at->toIso8601String(),
        ];
    }
}
