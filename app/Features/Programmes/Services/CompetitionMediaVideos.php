<?php

namespace App\Features\Programmes\Services;

use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Programmes\Models\CompetitionVideoHold;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Throwable;

class CompetitionMediaVideos
{
    public function __construct(
        private readonly CompetitionProgrammeNodes $nodes,
        private readonly CompetitionVideoNames $names,
    ) {}

    public function eventForPrefix(string $prefix): ?SchedulingEvent
    {
        if (! preg_match('#^(\d{4})/([^/]+)$#', trim($prefix, '/'), $parts)) {
            return null;
        }

        return SchedulingEvent::query()->where('event_type', 'competition')
            ->whereYear('event_date', (int) $parts[1])->whereNull('cancelled_at')->get()
            ->first(function (SchedulingEvent $event) use ($parts): bool {
                $plan = $this->plan($event);

                return $plan && strcasecmp((string) ($plan->snapshot['event_code'] ?? ''), $parts[2]) === 0;
            });
    }

    /** @return array{linked:int,existing:int,unmatched:list<string>,prefix:string} */
    public function sync(SchedulingEvent $event): array
    {
        $plan = $this->plan($event);
        abort_unless($plan && $event->event_date, 404, 'Prepare this competition before linking its videos.');
        $eventCode = (string) ($plan->snapshot['event_code'] ?? '');
        abort_if($eventCode === '', 409, 'The prepared competition does not have an event code.');
        $prefix = $event->event_date->format('Y').'/'.$eventCode;
        $disk = (string) config('media.competition_disk');
        $nodes = $this->nodes->nodes($plan);
        $nodeMap = collect($nodes)->keyBy('uuid');
        $keys = collect(Storage::disk($disk)->files($prefix))
            ->filter(fn (string $key): bool => in_array(strtolower(pathinfo($key, PATHINFO_EXTENSION)), ['mp4', 'm4v', 'mov', 'mxf'], true))
            ->sortBy(fn (string $key): string => $key, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $linked = 0;
        $existing = 0;
        $unmatched = [];

        DB::transaction(function () use ($keys, $event, $eventCode, $nodes, $nodeMap, $disk, $prefix, &$linked, &$existing, &$unmatched): void {
            foreach ($keys as $sortOrder => $key) {
                $itemUuid = $this->names->itemUuid(basename($key), $eventCode, $nodes);
                $node = $itemUuid ? $nodeMap->get($itemUuid) : null;
                if (! $itemUuid || ! $node) {
                    $unmatched[] = basename($key);

                    continue;
                }
                $held = CompetitionVideoHold::where('scheduling_event_id', $event->id)
                    ->where('item_uuid', $itemUuid)->where('is_held', true)->exists();
                $collection = MediaCollection::withTrashed()->firstOrCreate([
                    'uuid' => Uuid::uuid5($event->uuid, 'customer-video-routine/'.$itemUuid)->toString(),
                ], [
                    'competition_id' => $event->id, 'name' => $node['title'], 'media_type' => 'video',
                    'catalogue_mode' => 'hybrid', 'status' => 'published', 'visibility' => $held ? 'private' : 'customer',
                    'storage_disk' => $disk, 'storage_prefix' => $prefix.'/', 'published_at' => now(),
                    'metadata' => ['item_uuid' => $itemUuid, 'source' => 'competition_media'],
                ]);
                abort_unless(! $collection->trashed() && $collection->competition_id === $event->id
                    && ($collection->metadata['item_uuid'] ?? null) === $itemUuid, 409,
                    'An existing gallery record conflicts with this competition video.');
                $collection->update(['status' => 'published', 'visibility' => $held ? 'private' : 'customer',
                    'published_at' => $collection->published_at ?? now()]);

                $assetUuid = Uuid::uuid5($event->uuid, 'competition-media-video/'.$key)->toString();
                $asset = MediaAsset::withTrashed()->where('uuid', $assetUuid)->first();
                if ($asset) {
                    abort_unless(! $asset->trashed() && $asset->storage_disk === $disk && $asset->storage_key === $key
                        && $asset->media_collection_id === $collection->id, 409,
                        'An existing media record conflicts with this stored competition video.');
                    $existing++;

                    continue;
                }
                MediaAsset::create([
                    'uuid' => $assetUuid, 'media_collection_id' => $collection->id, 'media_type' => 'video',
                    'storage_disk' => $disk, 'storage_key' => $key, 'original_filename' => basename($key),
                    'display_name' => basename($key), 'status' => 'available', 'is_visible' => ! $held,
                    'sort_order' => $sortOrder, 'size_bytes' => $this->size($disk, $key),
                    'mime_type' => $this->mimeType(strtolower(pathinfo($key, PATHINFO_EXTENSION))),
                    'extension' => strtolower(pathinfo($key, PATHINFO_EXTENSION)),
                    'verified_at' => now(), 'metadata' => ['source' => 'competition_media'],
                ]);
                $linked++;
            }
        });

        return compact('linked', 'existing', 'unmatched', 'prefix');
    }

    private function plan(SchedulingEvent $event): ?ProgrammeUploadPlan
    {
        $programmeId = Programme::where('scheduling_event_id', $event->id)->value('id');

        return $programmeId ? ProgrammeUploadPlan::where('programme_id', $programmeId)->latest('id')->first() : null;
    }

    private function size(string $disk, string $key): ?int
    {
        try {
            return Storage::disk($disk)->size($key);
        } catch (Throwable) {
            return null;
        }
    }

    private function mimeType(string $extension): string
    {
        return match ($extension) {
            'mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime', 'mxf' => 'application/mxf',
            default => 'application/octet-stream',
        };
    }
}
