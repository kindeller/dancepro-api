<?php

namespace App\Features\Media\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Programmes\Services\CompetitionProgrammeNodes;
use App\Features\Programmes\Services\ProgrammePhotoPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

class ConcertPhotoGallerySetup
{
    public function __construct(private readonly CompetitionProgrammeNodes $nodes, private readonly ProgrammePhotoPreview $previews) {}

    public function editor(SchedulingEvent $event): array
    {
        abort_unless($event->event_type->value === 'concert', 404);
        $event->load('studio', 'concertBookingItem.concert.mediaCollections.assets');
        $concert = $event->concertBookingItem?->concert;
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
        $plan = $gallery ? ProgrammeUploadPlan::find($gallery->programme_upload_plan_id) : null;
        $items = collect($plan ? $this->nodes->nodes($plan) : [])->filter(fn (array $node) => ($node['kind'] ?? null) === 'item'
            && ($node['merged_into_uuid'] ?? null) === null && ($node['item_kind'] ?? 'dance') !== 'break')->values();
        $files = $gallery ? ProgrammePhotoFile::inGallery($gallery)->addSelect('contributions.item_uuid')
            ->orderBy('programme_photo_files.id')->get() : collect();
        $source = $items->map(function (array $item) use ($files, $concert): array {
            $itemFiles = $files->where('item_uuid', $item['uuid'])->values();
            $collection = $concert?->mediaCollections->first(fn (MediaCollection $album) => ($album->metadata['item_uuid'] ?? null) === $item['uuid']);

            return ['item' => $item, 'files' => $itemFiles, 'collection' => $collection];
        })->filter(fn (array $entry) => $entry['files']->isNotEmpty() || $entry['collection'])->values();

        return compact('event', 'concert', 'gallery', 'plan', 'source');
    }

    public function albumView(SchedulingEvent $event, string $albumUuid): array
    {
        abort_unless($event->event_type->value === 'concert', 404);
        $event->loadMissing('studio', 'concertBookingItem.concert');
        $concert = $event->concertBookingItem?->concert;
        abort_unless($concert, 404);
        $album = $concert->mediaCollections()->where('uuid', $albumUuid)->where('media_type', 'photo')->firstOrFail();
        $photos = $album->assets()->where('media_type', 'photo')->where('status', 'available')->where('is_visible', true)
            ->orderBy('sort_order')->orderBy('id')->paginate(48)->withQueryString();

        return compact('event', 'concert', 'album', 'photos');
    }

    public function sync(SchedulingEvent $event): int
    {
        $data = $this->editor($event);
        /** @var Concert|null $concert */
        $concert = $data['concert'];
        abort_unless($concert, 409, 'Link this scheduled event to its customer concert record before creating the gallery.');
        abort_unless($data['gallery'] && $data['plan'], 409, 'No verified concert photo uploads are available yet.');
        $count = 0;

        DB::transaction(function () use ($event, $concert, $data, &$count): void {
            SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            foreach ($data['source'] as $position => $entry) {
                if ($entry['files']->isEmpty()) {
                    continue;
                }
                $item = $entry['item'];
                $collection = $this->album($event, $concert, $data['gallery'], $item, $position);
                foreach ($entry['files'] as $index => $file) {
                    $this->syncAsset($event, $collection, $file, $index);
                }
                $count += $entry['files']->count();
            }
        });

        return $count;
    }

    /** Attach only the just-verified photo; do not rescan every previous upload. */
    public function syncVerifiedPhoto(SchedulingEvent $event, ProgrammePhotoFile $file): void
    {
        if ($event->event_type->value !== 'concert' || $file->status !== 'verified'
            || $file->verified_at === null) {
            return;
        }
        if ($file->duplicate_of_file_id !== null) {
            $file = ProgrammePhotoFile::whereKey($file->duplicate_of_file_id)
                ->where('programme_photo_gallery_id', $file->programme_photo_gallery_id)
                ->where('programme_photo_contribution_id', $file->programme_photo_contribution_id)
                ->where('status', 'verified')->whereNotNull('verified_at')->whereNull('duplicate_of_file_id')->firstOrFail();
        }
        $event->loadMissing('concertBookingItem.concert');
        $concert = $event->concertBookingItem?->concert;
        // Legacy standalone scheduling events have no customer concert to attach to.
        // They can use the repair sync after an administrator links the concert.
        if (! $concert) {
            return;
        }
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)
            ->findOrFail($file->programme_photo_gallery_id);
        $plan = ProgrammeUploadPlan::findOrFail($gallery->programme_upload_plan_id);
        $contribution = $file->contribution;
        abort_unless($contribution->programme_upload_plan_id === $plan->id, 409);
        $items = collect($this->nodes->nodes($plan))->filter(fn (array $node) => ($node['kind'] ?? null) === 'item'
            && ($node['merged_into_uuid'] ?? null) === null && ($node['item_kind'] ?? 'dance') !== 'break')->values();
        $position = $items->search(fn (array $item) => $item['uuid'] === $contribution->item_uuid);
        abort_if($position === false, 409, 'This photo does not belong to an active programme item.');

        DB::transaction(function () use ($event, $concert, $gallery, $items, $position, $file): void {
            SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $collection = $this->album($event, $concert, $gallery, $items[$position], $position);
            $index = ((int) $collection->assets()->max('sort_order')) + 1;
            $this->syncAsset($event, $collection, $file, $index);
        });
    }

    private function album(SchedulingEvent $event, Concert $concert, ProgrammePhotoGallery $gallery, array $item, int $position): MediaCollection
    {
        $uuid = Uuid::uuid5($concert->uuid, 'concert-photo-album/'.$item['uuid'])->toString();
        $name = $concert->isDressRehearsal() ? 'Dress Rehearsal Portraits' : trim(($item['folder_number'] ?? $item['number'] ?? '').' '.($item['folder_title'] ?? $item['title']));
        $collection = MediaCollection::firstOrCreate(['uuid' => $uuid], [
            'concert_id' => $concert->id, 'name' => $name ?: 'Photo album', 'media_type' => 'photo',
            'catalogue_mode' => 'managed', 'status' => 'draft', 'visibility' => 'private',
            'storage_disk' => 'local', 'storage_prefix' => 'programme-photo-galleries/'.$gallery->uuid.'/'.$item['uuid'],
            'sort_order' => $position, 'metadata' => ['source' => 'concert_programme_photo_gallery',
                'item_uuid' => $item['uuid'], 'scheduling_event_uuid' => $event->uuid],
        ]);
        abort_unless($collection->concert_id === $concert->id
            && ($collection->metadata['item_uuid'] ?? null) === $item['uuid'], 409);

        return $collection;
    }

    private function syncAsset(SchedulingEvent $event, MediaCollection $collection, ProgrammePhotoFile $file, int $index): void
    {
        abort_unless($file->storage_disk === 'local' && Storage::disk('local')->exists($file->storage_key), 409,
            $file->file_name.' is missing from private storage.');
        $thumbnail = $this->previews->key($file, 'thumbnail', $event);
        $assetUuid = Uuid::uuid5($file->uuid, 'concert-customer-photo')->toString();
        $asset = MediaAsset::withTrashed()->where('uuid', $assetUuid)->first();
        abort_if($asset && $asset->media_collection_id !== $collection->id, 409,
            'A photo identity is already attached to another customer album.');
        // Upload retries and later star changes must not restore removed media.
        if ($asset?->trashed()) {
            return;
        }
        MediaAsset::updateOrCreate(['uuid' => $assetUuid], [
            'media_collection_id' => $collection->id, 'media_type' => 'photo',
            'storage_disk' => $file->storage_disk, 'storage_key' => $file->storage_key,
            'thumbnail_storage_disk' => 'local', 'thumbnail_storage_key' => $thumbnail,
            'original_filename' => $file->file_name, 'display_name' => $asset?->display_name ?? $file->file_name,
            'status' => $asset?->status ?? 'available', 'is_visible' => $asset?->is_visible ?? ($collection->published_at === null), 'sort_order' => $asset?->sort_order ?? $index,
            'size_bytes' => $file->byte_count, 'mime_type' => 'image/jpeg', 'extension' => 'jpg',
            'verified_at' => $file->verified_at, 'metadata' => array_merge($asset?->metadata ?? [], [
                'programme_photo_file_uuid' => $file->uuid,
                'is_top_pic' => $file->is_top_pic,
            ]),
        ]);
        if ($collection->cover_media_asset_id === null) {
            $collection->update(['cover_media_asset_id' => $collection->assets()
                ->where('media_type', 'photo')->where('status', 'available')->where('is_visible', true)
                ->orderBy('sort_order')->orderBy('id')->value('id')]);
        }
    }

    public function updateAlbum(SchedulingEvent $event, MediaCollection $collection, array $input): void
    {
        $event->load('concertBookingItem.concert');
        abort_unless($event->concertBookingItem?->concert?->id === $collection->concert_id
            && $collection->media_type->value === 'photo' && $collection->published_at === null, 409,
            'Only unreleased photo albums for this concert can be edited.');
        $collection->update($input);
    }
}
