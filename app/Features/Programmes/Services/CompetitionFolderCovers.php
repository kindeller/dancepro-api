<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Collection;

class CompetitionFolderCovers
{
    public function apply(array $children, Collection $nodes, SchedulingEvent $event, ?array $allowed = null): array
    {
        if ($children === []) {
            return $children;
        }

        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
        if (! $gallery) {
            return $children;
        }

        $nodes = $nodes->keyBy('uuid');
        $files = ProgrammePhotoFile::inGallery($gallery)
            ->where('programme_photo_files.status', 'verified')
            ->whereNotNull('programme_photo_files.verified_at')
            ->whereNotNull('programme_photo_files.storage_key')
            ->addSelect('contributions.item_uuid')
            ->orderBy('programme_photo_files.id')
            ->get()
            ->groupBy('item_uuid');

        foreach ($children as &$child) {
            $itemUuids = array_values(array_filter(
                $this->descendantItemUuids($child['uuid'], $nodes),
                fn (string $uuid): bool => $allowed === null || isset($allowed[$uuid]),
            ));
            $candidates = collect($itemUuids)->flatMap(fn (string $uuid) => $files->get($uuid, collect()));
            $topPics = $candidates->where('is_top_pic', true)->values();

            if ($child['kind'] === 'item') {
                $cover = $topPics->first() ?? $candidates->first();
            } else {
                $pool = $topPics->isNotEmpty() ? $topPics : $candidates->values();
                $cover = $pool->isEmpty() ? null : $pool->get(abs(crc32($child['uuid'])) % $pool->count());
            }

            $child['cover_uuid'] = $cover?->uuid;
        }
        unset($child);

        return $children;
    }

    private function descendantItemUuids(string $uuid, Collection $nodes): array
    {
        $node = $nodes->get($uuid);
        if (($node['kind'] ?? null) === 'item') {
            return [$uuid];
        }

        return $nodes->filter(fn (array $candidate) => ($candidate['parent_uuid'] ?? null) === $uuid)
            ->flatMap(fn (array $candidate) => $this->descendantItemUuids($candidate['uuid'], $nodes))
            ->values()->all();
    }
}
