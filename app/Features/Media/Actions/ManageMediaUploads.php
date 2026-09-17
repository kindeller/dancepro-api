<?php

namespace App\Features\Media\Actions;

use App\Features\Concerts\Support\ConcertMediaPath;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaUpload;
use App\Features\Media\Services\S3MediaStorage;
use App\Features\Media\Services\VerifyHlsPackage;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaIngestPath;
use App\Features\Media\Support\MediaUploadDestination;
use App\Features\Media\Support\MediaUploadKind;
use App\Features\Media\Support\MediaUploadStatus;
use App\Models\User;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageMediaUploads
{
    public function __construct(
        private readonly ConcertMediaPath $mediaPath,
        private readonly MediaIngestPath $ingestPath,
        private readonly S3MediaStorage $storage,
        private readonly RecordMediaIngestEvent $recordEvent,
        private readonly MediaUploadDestination $destination,
        private readonly VerifyHlsPackage $hls,
    ) {}

    /**
     * @param  list<array{relative_path: string, content_type: string, size_bytes: int, checksum_sha256: string}>  $files
     * @return array<string, mixed>
     */
    public function createUploadBatch(MediaAsset $asset, User $user, array $files, string $idempotencyKey): array
    {
        $this->ensureWritable($asset);
        $this->validateIdempotencyKey($idempotencyKey);
        $normalised = [];

        foreach ($files as $file) {
            $path = $this->ingestPath->validate($file['relative_path']);
            if ($file['content_type'] !== $this->ingestPath->contentTypeFor($path)) {
                throw ValidationException::withMessages(['files' => "The content type does not match {$path}."]);
            }
            $this->validateBase64Checksum($file['checksum_sha256'], 32, 'files');
            if ($path === 'stream/master.m3u8' && ! $this->hlsChildrenAreComplete($asset)) {
                throw ValidationException::withMessages(['files' => 'The HLS master manifest can be uploaded only after a child playlist and media segment are complete.']);
            }
            $normalised[] = $file + ['storage_key' => $this->keyFor($asset, $path)];
        }

        $upload = MediaUpload::query()->firstOrCreate(
            ['user_id' => $user->id, 'idempotency_key' => $idempotencyKey, 'kind' => MediaUploadKind::SingleBatch],
            [
                'media_asset_id' => $asset->id,
                'status' => MediaUploadStatus::Pending,
                'storage_disk' => $asset->storage_disk,
                'files' => $normalised,
                'expires_at' => now()->addHours((int) config('media.upload_record_ttl_hours')),
            ],
        );

        if ($upload->media_asset_id !== $asset->id || $upload->files !== $normalised) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'This idempotency key was already used for a different upload request.']);
        }
        if ($upload->status !== MediaUploadStatus::Pending) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'This upload batch is already closed.']);
        }

        $expiresAt = now()->addMinutes((int) config('media.upload_url_ttl_minutes'));
        $objects = collect($normalised)->map(function (array $file) use ($asset, $expiresAt): array {
            $signed = $this->storage->presignPut(
                $asset->storage_disk,
                $file['storage_key'],
                $file['content_type'],
                $file['checksum_sha256'],
                $expiresAt,
            );

            return [
                'relative_path' => $file['relative_path'],
                'method' => 'PUT',
                'url' => $signed['url'],
                'headers' => $signed['headers'],
            ];
        })->all();

        $this->recordEvent->handle($user, $asset, 'media_upload.batch_created', ['upload_uuid' => $upload->uuid, 'file_count' => count($normalised)]);

        return ['upload_batch_uuid' => $upload->uuid, 'expires_at' => $expiresAt->toISOString(), 'objects' => $objects];
    }

    public function completeUploadBatch(MediaAsset $asset, MediaUpload $upload, User $user): MediaUpload
    {
        $this->ensureUploadBelongsTo($asset, $upload, MediaUploadKind::SingleBatch);
        if ($upload->status === MediaUploadStatus::Completed) {
            return $upload;
        }
        if ($upload->status !== MediaUploadStatus::Pending) {
            throw ValidationException::withMessages(['upload' => 'This upload batch cannot be completed.']);
        }

        foreach ($upload->files ?? [] as $file) {
            $head = $this->storage->head($upload->storage_disk, $file['storage_key']);
            if ($head === null || $head['size'] !== (int) $file['size_bytes']) {
                throw ValidationException::withMessages(['upload' => "The uploaded object {$file['relative_path']} is missing or has the wrong size."]);
            }
            if ($head['checksum_sha256'] !== null && ! hash_equals($file['checksum_sha256'], $head['checksum_sha256'])) {
                throw ValidationException::withMessages(['upload' => "The checksum for {$file['relative_path']} does not match."]);
            }
        }

        $upload->update(['status' => MediaUploadStatus::Completed, 'completed_at' => now()]);
        $this->recordEvent->handle($user, $asset, 'media_upload.batch_completed', ['upload_uuid' => $upload->uuid]);

        return $upload->refresh();
    }

    /**
     * @param  array{relative_path: string, content_type: string, size_bytes: int, checksum_algorithm: string, checksum: string}  $data
     * @return array<string, mixed>
     */
    public function startMultipart(MediaAsset $asset, User $user, array $data, string $idempotencyKey): array
    {
        $this->ensureWritable($asset);
        $this->validateIdempotencyKey($idempotencyKey);
        $path = $this->ingestPath->validate($data['relative_path']);
        $this->validateBase64Checksum($data['checksum'], 8, 'checksum');
        $key = $this->keyFor($asset, $path);

        $upload = MediaUpload::query()->where([
            'user_id' => $user->id,
            'idempotency_key' => $idempotencyKey,
            'kind' => MediaUploadKind::Multipart->value,
        ])->first();

        if (! $upload) {
            $provider = $this->storage->startMultipart($asset->storage_disk, $key, $data['content_type']);
            $upload = MediaUpload::query()->create([
                'media_asset_id' => $asset->id,
                'user_id' => $user->id,
                'kind' => MediaUploadKind::Multipart,
                'status' => MediaUploadStatus::Pending,
                'idempotency_key' => $idempotencyKey,
                'relative_path' => $path,
                'storage_disk' => $asset->storage_disk,
                'storage_key' => $key,
                'provider_upload_id' => $provider['upload_id'],
                'content_type' => $data['content_type'],
                'size_bytes' => $data['size_bytes'],
                'checksum_algorithm' => $data['checksum_algorithm'],
                'checksum' => $data['checksum'],
                'expires_at' => now()->addHours((int) config('media.upload_record_ttl_hours')),
            ]);
        }

        if ($upload->media_asset_id !== $asset->id
            || $upload->relative_path !== $path
            || $upload->size_bytes !== (int) $data['size_bytes']
            || $upload->checksum !== $data['checksum']) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'This idempotency key was already used for a different multipart upload.']);
        }
        if ($upload->status !== MediaUploadStatus::Pending) {
            throw ValidationException::withMessages(['upload' => 'This multipart upload is already closed.']);
        }

        $this->recordEvent->handle($user, $asset, 'media_upload.multipart_started', ['upload_uuid' => $upload->uuid, 'relative_path' => $path]);

        return [
            'upload_uuid' => $upload->uuid,
            'relative_path' => $path,
            'part_size_bytes' => (int) config('media.multipart_part_size_bytes'),
            'expires_at' => $upload->expires_at?->toISOString(),
        ];
    }

    /**
     * @param  list<array{part_number: int, checksum_crc64nvme: string}>  $parts
     * @return array<string, mixed>
     */
    public function createPartRequests(MediaAsset $asset, MediaUpload $upload, array $parts): array
    {
        $this->ensureUploadBelongsTo($asset, $upload, MediaUploadKind::Multipart);
        if ($upload->status !== MediaUploadStatus::Pending || $upload->expires_at?->isPast()) {
            throw ValidationException::withMessages(['upload' => 'This multipart upload is no longer active.']);
        }

        $expiresAt = now()->addMinutes((int) config('media.upload_url_ttl_minutes'));
        $requests = collect($parts)->sortBy('part_number')->map(function (array $part) use ($upload, $expiresAt): array {
            $this->validateBase64Checksum($part['checksum_crc64nvme'], 8, 'parts');
            $signed = $this->storage->presignPart(
                $upload->storage_disk,
                (string) $upload->storage_key,
                (string) $upload->provider_upload_id,
                (int) $part['part_number'],
                $part['checksum_crc64nvme'],
                $expiresAt,
            );

            return [
                'part_number' => (int) $part['part_number'],
                'method' => 'PUT',
                'url' => $signed['url'],
                'headers' => $signed['headers'],
            ];
        })->values()->all();

        return ['upload_uuid' => $upload->uuid, 'expires_at' => $expiresAt->toISOString(), 'parts' => $requests];
    }

    /**
     * @param  list<array{part_number: int, etag: string, checksum_crc64nvme: string}>  $parts
     */
    public function completeMultipart(MediaAsset $asset, MediaUpload $upload, User $user, array $parts, string $checksum): MediaUpload
    {
        $this->ensureUploadBelongsTo($asset, $upload, MediaUploadKind::Multipart);
        if ($upload->status === MediaUploadStatus::Completed) {
            return $upload;
        }
        if ($upload->status !== MediaUploadStatus::Pending || ! hash_equals((string) $upload->checksum, $checksum)) {
            throw ValidationException::withMessages(['upload' => 'The multipart upload is closed or its full-object checksum does not match.']);
        }
        $this->validateBase64Checksum($checksum, 8, 'checksum_crc64nvme');

        $ordered = collect($parts)->sortBy('part_number')->values();
        $expected = range(1, $ordered->count());
        if ($ordered->pluck('part_number')->map(fn ($number): int => (int) $number)->all() !== $expected) {
            throw ValidationException::withMessages(['parts' => 'Completed multipart parts must be contiguous and start at part 1.']);
        }

        $providerParts = $ordered->map(function (array $part): array {
            $this->validateBase64Checksum($part['checksum_crc64nvme'], 8, 'parts');

            return [
                'PartNumber' => (int) $part['part_number'],
                'ETag' => $part['etag'],
                'ChecksumCRC64NVME' => $part['checksum_crc64nvme'],
            ];
        })->all();

        $head = $this->storage->head($upload->storage_disk, (string) $upload->storage_key);
        if ($head === null) {
            try {
                $this->storage->completeMultipart(
                    $upload->storage_disk,
                    (string) $upload->storage_key,
                    (string) $upload->provider_upload_id,
                    $providerParts,
                    $checksum,
                    (int) $upload->size_bytes,
                );
            } catch (AwsException $exception) {
                // S3 may have committed the object before a response was lost.
                $head = $this->storage->head($upload->storage_disk, (string) $upload->storage_key);
                if (! $this->matchesMultipartObject($upload, $head)) {
                    throw $exception;
                }
            }

            $head ??= $this->storage->head($upload->storage_disk, (string) $upload->storage_key);
        }

        if (! $this->matchesMultipartObject($upload, $head)) {
            throw ValidationException::withMessages(['upload' => 'The multipart object size or full-object checksum could not be verified.']);
        }

        DB::transaction(function () use ($upload, $ordered, $user, $asset): void {
            $locked = MediaUpload::query()->lockForUpdate()->findOrFail($upload->id);
            if ($locked->status === MediaUploadStatus::Completed) {
                return;
            }
            if ($locked->status !== MediaUploadStatus::Pending) {
                throw ValidationException::withMessages(['upload' => 'This multipart upload is no longer pending.']);
            }
            $locked->update(['status' => MediaUploadStatus::Completed, 'completed_at' => now(), 'metadata' => ['parts' => $ordered->count()]]);
            $this->recordEvent->handle($user, $asset, 'media_upload.multipart_completed', ['upload_uuid' => $locked->uuid]);
        });

        return $upload->refresh();
    }

    private function matchesMultipartObject(MediaUpload $upload, ?array $head): bool
    {
        return $head !== null
            && $head['size'] === $upload->size_bytes
            && is_string($head['checksum_crc64nvme'] ?? null)
            && hash_equals((string) $upload->checksum, $head['checksum_crc64nvme']);
    }

    public function abortMultipart(MediaAsset $asset, MediaUpload $upload, User $user): MediaUpload
    {
        $this->ensureUploadBelongsTo($asset, $upload, MediaUploadKind::Multipart);
        if ($upload->status === MediaUploadStatus::Aborted) {
            return $upload;
        }
        if ($upload->status !== MediaUploadStatus::Pending) {
            throw ValidationException::withMessages(['upload' => 'Only an unfinished multipart upload can be aborted.']);
        }

        $this->storage->abortMultipart($upload->storage_disk, (string) $upload->storage_key, (string) $upload->provider_upload_id);
        $upload->update(['status' => MediaUploadStatus::Aborted, 'aborted_at' => now()]);
        $this->recordEvent->handle($user, $asset, 'media_upload.multipart_aborted', ['upload_uuid' => $upload->uuid]);

        return $upload->refresh();
    }

    /**
     * @param  list<string>  $expectedOutputs
     */
    public function finalize(MediaAsset $asset, User $user, array $expectedOutputs): MediaAsset
    {
        if ($asset->status === MediaAssetStatus::Available && $asset->verified_at !== null) {
            return $asset;
        }
        $this->ensureWritable($asset);
        $reserved = data_get($asset->metadata, 'ingest.expected_outputs', []);
        $given = $expectedOutputs;
        sort($reserved);
        sort($given);
        if ($reserved !== $given) {
            throw ValidationException::withMessages(['expected_outputs' => 'Finalisation outputs must match the reserved asset contract.']);
        }

        $requiredPaths = ['original/video.mp4', 'stream/fallback.mp4'];
        if (in_array('poster', $given, true)) {
            $requiredPaths[] = 'thumbnail/poster.png';
        }
        $hasHls = count(array_intersect($given, ['hls_720p', 'hls_480p'])) > 0;
        if ($hasHls) {
            $requiredPaths[] = 'stream/master.m3u8';
        }

        $metadata = [];
        foreach ($requiredPaths as $path) {
            $head = $this->storage->head($asset->storage_disk, $this->keyFor($asset, $path));
            if ($head === null || $head['size'] < 1) {
                throw ValidationException::withMessages(['expected_outputs' => "The required output {$path} is missing."]);
            }
            $metadata[$path] = $head;
        }
        if ($hasHls) {
            $this->hls->verify($asset, $given);
        }

        return DB::transaction(function () use ($asset, $user, $metadata): MediaAsset {
            $original = $metadata['original/video.mp4'];
            $asset->update([
                'status' => MediaAssetStatus::Available,
                'is_visible' => false,
                'size_bytes' => $original['size'],
                'mime_type' => $original['content_type'] ?: 'video/mp4',
                'verified_at' => now(),
            ]);
            $this->recordEvent->handle($user, $asset, 'media_asset.finalized', ['verified_paths' => array_keys($metadata)]);

            return $asset->refresh();
        });
    }

    private function hlsChildrenAreComplete(MediaAsset $asset): bool
    {
        $paths = $asset->uploads()
            ->where('kind', MediaUploadKind::SingleBatch->value)
            ->where('status', MediaUploadStatus::Completed->value)
            ->get()
            ->flatMap(fn (MediaUpload $upload) => collect($upload->files ?? [])->pluck('relative_path'));

        return $paths->contains(fn (string $path): bool => $path !== 'stream/master.m3u8' && str_ends_with($path, '.m3u8'))
            && $paths->contains(fn (string $path): bool => str_ends_with($path, '.m4s') || str_ends_with($path, '.ts'));
    }

    private function ensureWritable(MediaAsset $asset): void
    {
        $this->destination->ensureAllowed($asset->storage_disk);
        $this->destination->ensureCollectionAllowed($asset->collection);
        if ($asset->status !== MediaAssetStatus::Processing || $asset->verified_at !== null) {
            throw ValidationException::withMessages(['asset' => 'Only an unfinished processing asset can receive uploads.']);
        }
    }

    private function ensureUploadBelongsTo(MediaAsset $asset, MediaUpload $upload, MediaUploadKind $kind): void
    {
        $this->destination->ensureAllowed($asset->storage_disk);
        $this->destination->ensureCollectionAllowed($asset->collection);
        $this->destination->ensureAllowed($upload->storage_disk);
        if ($upload->media_asset_id !== $asset->id || $upload->kind !== $kind) {
            throw ValidationException::withMessages(['upload' => 'The upload does not belong to this asset.']);
        }
    }

    private function keyFor(MediaAsset $asset, string $relativePath): string
    {
        return $this->mediaPath->assetPrefix($asset).'/'.$relativePath;
    }

    private function validateIdempotencyKey(string $key): void
    {
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'A UUID Idempotency-Key header is required.']);
        }
    }

    private function validateBase64Checksum(string $checksum, int $bytes, string $field): void
    {
        $decoded = base64_decode($checksum, true);
        if ($decoded === false || strlen($decoded) !== $bytes) {
            throw ValidationException::withMessages([$field => "The {$field} checksum is not a valid base64-encoded {$bytes}-byte value."]);
        }
    }
}
