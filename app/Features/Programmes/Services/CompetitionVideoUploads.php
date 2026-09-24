<?php

namespace App\Features\Programmes\Services;

use App\Features\Crew\Models\CrewRole;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\S3MediaStorage;
use App\Features\Media\Support\MediaUploadDestination;
use App\Features\Orders\Services\ReleasePendingCompetitionVideos;
use App\Features\Programmes\Models\CompetitionVideoBatch;
use App\Features\Programmes\Models\CompetitionVideoFile;
use App\Features\Programmes\Models\CompetitionVideoHold;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class CompetitionVideoUploads
{
    public function __construct(
        private readonly CompetitionProgrammeNodes $nodes,
        private readonly CompetitionVideoNames $names,
        private readonly S3MediaStorage $storage,
        private readonly MediaUploadDestination $destination,
        private readonly ProgrammeUploadRoles $roles,
        private readonly ReleasePendingCompetitionVideos $pendingOrders,
    ) {}

    public function begin(SchedulingEvent $event, User $actor, array $input): CompetitionVideoBatch
    {
        $plan = $this->currentPlan($event, $input['plan_uuid']);
        $role = collect($this->roles->forEvent($event)['roles'])->first(fn (array $role) => $role['role_uuid'] === strtolower($input['role_uuid']) && $role['media_type'] === 'video');
        abort_unless($role, 422, 'Choose the competition videographer event role.');
        $batch = CompetitionVideoBatch::firstOrCreate(['uuid' => strtolower($input['batch_uuid'])], [
            'scheduling_event_id' => $event->id, 'programme_upload_plan_id' => $plan->id,
            'crew_role_id' => CrewRole::where('uuid', $role['role_uuid'])->value('id'),
            'created_by_user_id' => $actor->id, 'status' => 'open',
        ]);
        abort_unless($batch->scheduling_event_id === $event->id && $batch->programme_upload_plan_id === $plan->id
            && $batch->created_by_user_id === $actor->id && $batch->crew_role_id === CrewRole::where('uuid', $role['role_uuid'])->value('id'),
            409, 'This batch belongs to another event, programme, role or uploader.');

        return $batch;
    }

    public function reserve(SchedulingEvent $event, CompetitionVideoBatch $batch, array $input): array
    {
        $this->assertOpen($event, $batch);
        $plan = ProgrammeUploadPlan::findOrFail($batch->programme_upload_plan_id);
        $name = $input['file_name'];
        $itemUuid = $this->names->itemUuid($name, (string) ($plan->snapshot['event_code'] ?? ''), $this->nodes->nodes($plan));
        $existing = CompetitionVideoFile::where('scheduling_event_id', $event->id)->where('sha256', $input['sha256'])->first();
        if ($existing) {
            abort_unless($existing->byte_count === (int) $input['byte_count'], 409, 'This video checksum has a different file size.');
            abort_unless($itemUuid === null || $existing->item_uuid === null || $existing->item_uuid === $itemUuid,
                409, 'These video bytes are already assigned to another routine. Review the filename and assignment.');
            abort_unless($existing->status === 'verified' || $existing->competition_video_batch_id === $batch->id,
                409, 'This file is reserved in another open batch. Resume that batch before uploading it.');
            $extension = strtolower(pathinfo($existing->original_filename, PATHINFO_EXTENSION));
            $upload = $existing->status === 'verified' ? null : $this->storage->presignPut(
                $existing->storage_disk, $existing->storage_key, $this->mimeType($extension),
                base64_encode(hex2bin($existing->sha256)), now()->addMinutes(15),
            );

            return ['file' => $existing, 'already_uploaded' => $existing->status === 'verified', 'upload' => $upload];
        }
        if ($itemUuid && $this->releasedCollection($event, $itemUuid)) {
            throw ValidationException::withMessages(['file_name' => 'This routine already has a completed video batch. Contact an administrator before adding later clips.']);
        }
        $disk = (string) config('media.competition_disk');
        $this->destination->ensureCompetitionAllowed($disk);
        $uuid = strtolower($input['file_uuid']);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $eventCode = (string) ($plan->snapshot['event_code'] ?? '');
        abort_if($eventCode === '' || ! $event->event_date, 409, 'The prepared competition needs an event code and date before video upload.');
        $key = $event->event_date->format('Y').'/'.$eventCode.'/'.$name;
        if (Storage::disk($disk)->exists($key)) {
            throw ValidationException::withMessages([
                'file_name' => 'A video with this filename already exists in Competition Media. Link the existing video instead of replacing it.',
            ]);
        }
        $file = CompetitionVideoFile::firstOrCreate(['uuid' => $uuid], [
            'competition_video_batch_id' => $batch->id, 'scheduling_event_id' => $event->id,
            'item_uuid' => $itemUuid, 'original_filename' => $name, 'sha256' => $input['sha256'],
            'byte_count' => $input['byte_count'], 'storage_disk' => $disk, 'storage_key' => $key,
            'status' => 'reserved',
        ]);
        abort_unless($file->competition_video_batch_id === $batch->id && $file->sha256 === $input['sha256']
            && $file->byte_count === (int) $input['byte_count'] && $file->original_filename === $name,
            409, 'This video reservation has changed.');
        $upload = $file->status === 'verified' ? null : $this->storage->presignPut(
            $disk, $key, $this->mimeType($extension), base64_encode(hex2bin($file->sha256)), now()->addMinutes(15),
        );

        return ['file' => $file, 'already_uploaded' => $file->status === 'verified', 'upload' => $upload];
    }

    public function verify(SchedulingEvent $event, CompetitionVideoBatch $batch, CompetitionVideoFile $file): CompetitionVideoFile
    {
        $this->assertOpen($event, $batch);
        abort_unless($file->competition_video_batch_id === $batch->id, 404);
        if ($file->status === 'verified') {
            return $file;
        }
        $head = $this->storage->head($file->storage_disk, $file->storage_key);
        $expected = base64_encode(hex2bin($file->sha256));
        abort_unless($head && $head['size'] === $file->byte_count
            && is_string($head['checksum_sha256']) && hash_equals($expected, $head['checksum_sha256']),
            409, 'The uploaded video has not passed size and checksum verification.');
        $file->update(['status' => 'verified', 'verified_at' => now()]);

        return $file->refresh();
    }

    public function complete(SchedulingEvent $event, CompetitionVideoBatch $batch): array
    {
        abort_unless($batch->scheduling_event_id === $event->id, 404);
        if ($batch->status === 'complete') {
            $this->releasePending($event, $batch);

            return $this->summary($batch);
        }
        $this->assertOpen($event, $batch);

        $summary = DB::transaction(function () use ($event, $batch): array {
            $batch = CompetitionVideoBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            abort_unless($batch->files()->exists(), 409, 'No videos are in this batch.');
            abort_unless(! $batch->files()->where('status', '!=', 'verified')->exists(), 409,
                'Verify every video before completing the batch.');
            abort_unless(! $batch->files()->whereNull('item_uuid')->exists(), 409,
                'Assign unmatched videos before completing the batch. They remain private.');
            $plan = ProgrammeUploadPlan::findOrFail($batch->programme_upload_plan_id);
            $nodes = collect($this->nodes->nodes($plan))->keyBy('uuid');
            foreach ($batch->files()->whereNotNull('item_uuid')->get()->groupBy('item_uuid') as $itemUuid => $files) {
                $node = $nodes->get($itemUuid);
                abort_unless($node && ($node['kind'] ?? null) === 'item', 409, 'A matched routine is no longer in the programme.');
                $collectionUuid = Uuid::uuid5($event->uuid, 'customer-video-routine/'.$itemUuid)->toString();
                $collection = MediaCollection::firstOrCreate(['uuid' => $collectionUuid], [
                    'competition_id' => $event->id, 'name' => $node['title'], 'media_type' => 'video',
                    'catalogue_mode' => 'managed', 'status' => 'draft', 'visibility' => 'private',
                    'storage_disk' => $files->first()->storage_disk,
                    'storage_prefix' => $event->event_date->format('Y').'/'.($plan->snapshot['event_code'] ?? '').'/',
                    'metadata' => ['item_uuid' => $itemUuid, 'source' => 'competition_video_upload'],
                ]);
                abort_unless($collection->competition_id === $event->id
                    && ($collection->metadata['item_uuid'] ?? null) === $itemUuid, 409);
                foreach ($files as $index => $file) {
                    $asset = MediaAsset::firstOrCreate(['uuid' => Uuid::uuid5($file->uuid, 'customer-video')->toString()], [
                        'media_collection_id' => $collection->id, 'media_type' => 'video',
                        'storage_disk' => $file->storage_disk, 'storage_key' => $file->storage_key,
                        'original_filename' => $file->original_filename, 'display_name' => $file->original_filename,
                        'status' => 'available', 'is_visible' => false, 'sort_order' => $index,
                        'size_bytes' => $file->byte_count, 'mime_type' => $this->mimeType(strtolower(pathinfo($file->original_filename, PATHINFO_EXTENSION))),
                        'extension' => strtolower(pathinfo($file->original_filename, PATHINFO_EXTENSION)),
                        'verified_at' => $file->verified_at, 'metadata' => ['competition_video_file_uuid' => $file->uuid],
                    ]);
                    abort_unless($asset->media_collection_id === $collection->id, 409);
                    $file->update(['media_asset_id' => $asset->id]);
                }
                $held = CompetitionVideoHold::where('scheduling_event_id', $event->id)->where('item_uuid', $itemUuid)->where('is_held', true)->exists();
                $collection->update(['status' => 'published', 'visibility' => $held ? 'private' : 'customer',
                    'published_at' => $collection->published_at ?? now()]);
                $collection->assets()->update(['is_visible' => ! $held]);
            }
            $batch->update(['status' => 'complete', 'completed_at' => now()]);

            return $this->summary($batch);
        });
        $this->releasePending($event, $batch);

        return $summary;
    }

    public function summary(CompetitionVideoBatch $batch): array
    {
        return ['batch_uuid' => $batch->uuid, 'status' => $batch->status,
            'verified' => $batch->files()->where('status', 'verified')->count(),
            'needs_assignment' => $batch->files()->whereNull('item_uuid')->count(),
            'completed_at' => $batch->completed_at?->toIso8601String()];
    }

    public function assign(SchedulingEvent $event, CompetitionVideoFile $file, string $itemUuid): void
    {
        abort_unless($file->scheduling_event_id === $event->id && $file->batch->status === 'open', 409,
            'Only files in an open batch can be assigned.');
        $plan = ProgrammeUploadPlan::findOrFail($file->batch->programme_upload_plan_id);
        $item = collect($this->nodes->nodes($plan))->first(fn (array $node) => $node['uuid'] === strtolower($itemUuid)
            && ($node['kind'] ?? null) === 'item' && ($node['merged_into_uuid'] ?? null) === null);
        abort_unless($item, 422, 'Choose a routine in this competition programme.');
        abort_unless(! $this->releasedCollection($event, $item['uuid']), 409,
            'This routine is already released. Review later clips separately.');
        $file->update(['item_uuid' => $item['uuid']]);
    }

    private function assertOpen(SchedulingEvent $event, CompetitionVideoBatch $batch): void
    {
        abort_unless($event->event_type->value === 'competition' && $batch->scheduling_event_id === $event->id, 404);
        abort_unless($batch->status === 'open', 409, 'This video batch is complete.');
    }

    private function currentPlan(SchedulingEvent $event, string $uuid): ProgrammeUploadPlan
    {
        abort_unless($event->event_type->value === 'competition', 422, 'Choose a competition event.');
        $programme = Programme::where('scheduling_event_id', $event->id)->firstOrFail();
        $revision = $programme->revisions()->orderByDesc('version')->firstOrFail();
        $plan = ProgrammeUploadPlan::where('programme_id', $programme->id)
            ->where('programme_revision_id', $revision->id)->where('uuid', strtolower($uuid))->firstOrFail();

        return $plan;
    }

    private function releasedCollection(SchedulingEvent $event, string $itemUuid): bool
    {
        return MediaCollection::where('competition_id', $event->id)->where('media_type', 'video')
            ->where('metadata->item_uuid', $itemUuid)->where('status', 'published')->exists();
    }

    private function mimeType(string $extension): string
    {
        return match ($extension) {
            'mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime', 'mxf' => 'application/mxf',
            default => 'application/octet-stream',
        };
    }

    private function releasePending(SchedulingEvent $event, CompetitionVideoBatch $batch): void
    {
        foreach ($batch->files()->whereNotNull('item_uuid')->pluck('item_uuid')->unique() as $itemUuid) {
            $collection = MediaCollection::where('competition_id', $event->id)->where('media_type', 'video')
                ->where('metadata->item_uuid', $itemUuid)->first();
            if ($collection) {
                $this->pendingOrders->forCollection($event, $collection);
            }
        }
    }
}
