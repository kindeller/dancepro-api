<?php

namespace App\Features\Operations\Services;

use App\Features\Operations\Models\OperationalResource;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CrewMobileDocuments
{
    public function __construct(private readonly OperationsFileStorage $files) {}

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

        return [
            'id' => $resource->uuid,
            'title' => $resource->title,
            'mime_type' => $this->files->disk()->mimeType($resource->file_path) ?: 'application/octet-stream',
            'bytes' => $this->files->disk()->size($resource->file_path),
            'checksum' => $this->checksum($resource->file_path),
            'updated_at' => $resource->updated_at->toIso8601String(),
            'offline_allowed' => true,
        ];
    }

    private function checksum(string $path): string
    {
        $stream = $this->files->disk()->readStream($path);
        abort_if($stream === false, 404);
        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        fclose($stream);

        return hash_final($hash);
    }
}
