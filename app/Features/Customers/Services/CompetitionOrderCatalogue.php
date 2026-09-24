<?php

namespace App\Features\Customers\Services;

use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Support\MediaType;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Services\CompetitionCustomerPreview;
use App\Features\Programmes\Services\ProgrammePhotoPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

class CompetitionOrderCatalogue
{
    public function __construct(private readonly CompetitionCustomerPreview $preview, private readonly ProgrammePhotoPreview $photoPreview) {}

    /** @return array{collection: MediaCollection, assets: Collection<int, MediaAsset>} */
    public function readyRoutine(SchedulingEvent $event, string $itemUuid): array
    {
        $routine = $this->preview->browse($event, $itemUuid, 'customer.competitions.image');
        abort_unless(($routine['selected']['kind'] ?? null) === 'item', 404);
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->firstOrFail();
        $files = ProgrammePhotoFile::inGallery($gallery)->where('contributions.item_uuid', $itemUuid)
            ->orderBy('programme_photo_files.id')->get();
        abort_if($files->isEmpty(), 409, 'This routine has no orderable photos.');
        foreach ($files as $file) {
            abort_unless($file->storage_disk === 'local' && Storage::disk('local')->exists($file->storage_key), 409,
                'A routine photo is temporarily unavailable.');
        }

        return DB::transaction(function () use ($event, $itemUuid, $routine, $gallery, $files): array {
            $collectionUuid = Uuid::uuid5($event->uuid, 'customer-photo-routine/'.$itemUuid)->toString();
            $collection = MediaCollection::firstOrCreate(['uuid' => $collectionUuid], [
                'competition_id' => $event->id,
                'name' => $routine['selected']['label'],
                'media_type' => MediaType::Photo,
                'catalogue_mode' => 'managed',
                'status' => 'published',
                'visibility' => 'customer',
                'storage_disk' => 'local',
                'storage_prefix' => 'programme-photo-galleries/'.$gallery->uuid.'/'.$itemUuid,
                'published_at' => now(),
                'metadata' => ['item_uuid' => $itemUuid, 'source' => 'programme_photo_gallery'],
            ]);
            abort_unless($collection->competition_id === $event->id
                && ($collection->metadata['item_uuid'] ?? null) === $itemUuid, 409);
            $assets = collect();
            foreach ($files as $index => $file) {
                $thumbnail = $this->photoPreview->key($file, 'thumbnail', $event);
                $assetUuid = Uuid::uuid5($file->uuid, 'customer-catalogue-photo')->toString();
                $asset = MediaAsset::firstOrCreate(['uuid' => $assetUuid], [
                    'media_collection_id' => $collection->id,
                    'media_type' => MediaType::Photo,
                    'storage_disk' => $file->storage_disk,
                    'storage_key' => $file->storage_key,
                    'original_filename' => $file->file_name,
                    'display_name' => $file->file_name,
                    'status' => 'available',
                    'is_visible' => true,
                    'sort_order' => $index,
                    'size_bytes' => $file->byte_count,
                    'mime_type' => 'image/jpeg',
                    'extension' => 'jpg',
                    'thumbnail_storage_disk' => 'local',
                    'thumbnail_storage_key' => $thumbnail,
                    'verified_at' => $file->verified_at,
                    'metadata' => ['programme_photo_file_uuid' => $file->uuid],
                ]);
                abort_unless($asset->media_collection_id === $collection->id
                    && ($asset->metadata['programme_photo_file_uuid'] ?? null) === $file->uuid, 409);
                $assets->push($asset);
            }

            return compact('collection', 'assets');
        });
    }
}
