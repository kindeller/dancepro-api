<?php

namespace App\Features\Programmes\Services;

use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\S3MediaStorage;
use App\Features\Media\Support\MediaUploadDestination;
use App\Features\Programmes\Models\ConcertVideoBatch;
use App\Features\Programmes\Models\ConcertVideoFile;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConcertVideoUploads
{
    public function __construct(private readonly S3MediaStorage $storage, private readonly MediaUploadDestination $destination) {}

    public function begin(SchedulingEvent $event, User $actor, array $input): ConcertVideoBatch
    {
        abort_unless($event->concertBookingItem?->concert && $event->concertBookingItem->item_type->value === 'concert' && $event->concertBookingItem->booking?->wants_concert_videography, 409, 'Link this event to its concert video booking before uploading.');
        $disk = (string) config('media.upload_disk');
        $this->destination->ensureAllowed($disk);
        $files = collect($input['files'])->sortBy('file_uuid')->map(fn ($file) => [
            'file_uuid' => strtolower($file['file_uuid']), 'file_name' => $file['file_name'],
            'byte_count' => (int) $file['byte_count'], 'sha256' => $file['sha256'],
        ])->values()->all();
        $hash = hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($event, $actor, $input, $files, $hash, $disk): ConcertVideoBatch {
            SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $batch = ConcertVideoBatch::where('uuid', strtolower($input['batch_uuid']))->first();
            if ($batch) {
                $this->assertBatch($event, $batch, $actor);
                abort_unless(hash_equals($batch->manifest_hash, $hash), 409, 'The file queue changed. Start a new concert upload queue.');

                return $batch;
            }
            $batch = ConcertVideoBatch::create(['uuid' => strtolower($input['batch_uuid']), 'scheduling_event_id' => $event->id,
                'created_by_user_id' => $actor->id, 'manifest_hash' => $hash]);
            foreach ($files as $file) {
                $previous = ConcertVideoFile::whereHas('batch', fn ($query) => $query->where('scheduling_event_id', $event->id))
                    ->where('sha256', $file['sha256'])->where('byte_count', $file['byte_count'])->whereNotNull('verified_at')->first();
                $batch->files()->create(['uuid' => $file['file_uuid'], 'original_filename' => $file['file_name'],
                    'byte_count' => $file['byte_count'], 'sha256' => $file['sha256'], 'storage_disk' => $previous?->storage_disk ?? $disk,
                    'storage_key' => $previous?->storage_key ?? 'concert-uploads/'.$event->uuid.'/'.$batch->uuid.'/'.$file['file_uuid'].'.mp4',
                    'verified_at' => $previous?->verified_at]);
            }

            return $batch;
        });
    }

    public function address(SchedulingEvent $event, ConcertVideoBatch $batch, ConcertVideoFile $file, User $actor): array
    {
        $this->assertBatch($event, $batch, $actor);
        abort_unless($file->concert_video_batch_id === $batch->id, 404);

        return ['file_uuid' => $file->uuid, 'verified' => $file->verified_at !== null,
            'upload' => $file->verified_at ? null : $this->storage->presignPut($file->storage_disk, $file->storage_key,
                'video/mp4', base64_encode(hex2bin($file->sha256)), now()->addHours(2))];
    }

    public function verify(SchedulingEvent $event, ConcertVideoBatch $batch, ConcertVideoFile $file, User $actor): void
    {
        $this->assertBatch($event, $batch, $actor);
        abort_unless($file->concert_video_batch_id === $batch->id, 404);
        if ($file->verified_at) {
            return;
        }
        $head = $this->storage->head($file->storage_disk, $file->storage_key);
        abort_unless($head && $head['size'] === $file->byte_count && is_string($head['checksum_sha256'])
            && hash_equals(base64_encode(hex2bin($file->sha256)), $head['checksum_sha256']), 409,
            'The video has not passed size and checksum verification. Resume the upload.');
        $file->update(['verified_at' => now()]);
    }

    public function complete(SchedulingEvent $event, ConcertVideoBatch $batch, User $actor): ConcertVideoBatch
    {
        return DB::transaction(function () use ($event, $batch, $actor): ConcertVideoBatch {
            SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $this->assertBatch($event, $batch, $actor);
            $batch = ConcertVideoBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->completed_at) {
                return $batch;
            }
            abort_unless($batch->files()->exists() && ! $batch->files()->whereNull('verified_at')->exists(), 409, 'Every queued video must be verified before Finished Uploading.');
            $concert = $event->concertBookingItem?->concert;
            abort_unless($concert, 409, 'The concert video record is missing.');
            $collection = MediaCollection::create(['concert_id' => $concert->id, 'name' => $event->name.' — edited videos',
                'media_type' => 'video', 'catalogue_mode' => 'managed', 'status' => 'draft', 'visibility' => 'private',
                'storage_disk' => $batch->files()->first()->storage_disk, 'storage_prefix' => 'concert-uploads/'.$event->uuid.'/'.$batch->uuid.'/',
                'created_by_user_id' => $actor->id, 'metadata' => ['concert_video_batch_uuid' => $batch->uuid]]);
            foreach ($batch->files()->orderBy('id')->get() as $index => $file) {
                $asset = MediaAsset::create(['media_collection_id' => $collection->id, 'media_type' => 'video',
                    'storage_disk' => $file->storage_disk, 'storage_key' => $file->storage_key,
                    'original_filename' => $file->original_filename, 'display_name' => $file->original_filename,
                    'status' => 'available', 'is_visible' => false, 'sort_order' => $index,
                    'size_bytes' => $file->byte_count, 'mime_type' => 'video/mp4', 'extension' => 'mp4',
                    'verified_at' => $file->verified_at, 'created_by_user_id' => $actor->id,
                    'metadata' => ['concert_video_file_uuid' => $file->uuid]]);
                $file->update(['media_asset_id' => $asset->id]);
            }
            $batch->update(['completed_at' => now(), 'completed_by_user_id' => $actor->id]);

            return $batch;
        });
    }

    public function summary(ConcertVideoBatch $batch): array
    {
        $files = $batch->files()->get();

        return ['batch_uuid' => $batch->uuid, 'queued' => $files->count(), 'verified' => $files->whereNotNull('verified_at')->count(),
            'completed_at' => $batch->completed_at?->toIso8601String()];
    }

    private function assertBatch(SchedulingEvent $event, ConcertVideoBatch $batch, User $actor): void
    {
        abort_unless($batch->scheduling_event_id === $event->id && $batch->created_by_user_id === $actor->id, 404);
        abort_unless(ConcertVideoBatch::where('scheduling_event_id', $event->id)->latest('id')->value('id') === $batch->id, 409, 'A newer concert video queue exists. Reload the event.');
        abort_if($event->cancelled_at || $event->concertBookingItem?->item_type?->value !== 'concert' || ! $event->concertBookingItem?->booking?->wants_concert_videography, 409, 'Concert video uploads are no longer required for this event.');
    }
}
