<?php

namespace App\Features\Media\Actions;

use App\Features\Concerts\Support\ConcertMediaPath;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaUpload;
use App\Features\Media\Services\S3MediaStorage;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaCollectionVisibility;
use App\Features\Media\Support\MediaUploadDestination;
use App\Features\Media\Support\MediaUploadKind;
use App\Features\Media\Support\MediaUploadStatus;
use Aws\Exception\AwsException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class DeleteManagedConcertMedia
{
    public function __construct(
        private readonly ConcertMediaPath $paths,
        private readonly MediaUploadDestination $destination,
        private readonly S3MediaStorage $storage,
    ) {}

    public function previewAsset(MediaAsset $asset): array
    {
        return $this->preview($asset->storage_disk, $this->paths->assetPrefix($asset).'/', collect([$asset]));
    }

    public function previewCollection(MediaCollection $collection): array
    {
        $this->destination->ensureCollectionAllowed($collection);

        return $this->preview($collection->storage_disk, $collection->uuid.'/', $collection->assets()->get());
    }

    public function digest(string $target, array $preview): string
    {
        return hash_hmac('sha256', json_encode([$target, $preview], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function deleteAsset(MediaAsset $asset, string $digest, bool $force = false): void
    {
        $preview = $this->previewAsset($asset);
        $this->verify($asset->storage_disk.':'.$asset->uuid, $preview, $digest, $force);
        $this->archiveAsset($asset);
        $this->cleanup($asset->storage_disk, $this->paths->assetPrefix($asset).'/', $preview);
        $asset->delete();
    }

    public function deleteCollection(MediaCollection $collection, string $digest, bool $force = false): void
    {
        $preview = $this->previewCollection($collection);
        $this->verify($collection->storage_disk.':'.$collection->uuid, $preview, $digest, $force);
        $assets = $collection->assets()->get();
        DB::transaction(function () use ($collection, $assets): void {
            $collection->update([
                'status' => MediaCollectionStatus::Archived,
                'visibility' => MediaCollectionVisibility::Private,
                'published_at' => null,
                'archived_at' => now(),
            ]);
            foreach ($assets as $asset) {
                $this->archiveAsset($asset);
            }
        });
        $this->cleanup($collection->storage_disk, $collection->uuid.'/', $preview);
        DB::transaction(function () use ($collection, $assets): void {
            foreach ($assets as $asset) {
                $asset->delete();
            }
            $collection->delete();
        });
    }

    /** @param Collection<int, MediaAsset> $assets */
    private function preview(string $disk, string $prefix, Collection $assets): array
    {
        $this->destination->ensureAllowed($disk);
        foreach ($assets as $asset) {
            $this->destination->ensureCollectionAllowed($asset->collection);
            if ($asset->storage_disk !== $disk || $asset->storage_disk !== $asset->collection->storage_disk) {
                throw ValidationException::withMessages(['asset' => 'This video uses a different storage disk and cannot be deleted here.']);
            }
        }

        $uploads = MediaUpload::query()->whereIn('media_asset_id', $assets->pluck('id'))
            ->whereIn('status', [MediaUploadStatus::Pending, MediaUploadStatus::Failed])->orderBy('uuid')->get();
        foreach ($uploads as $upload) {
            $asset = $assets->firstWhere('id', $upload->media_asset_id);
            if ($upload->storage_disk !== $disk
                || ($upload->kind === MediaUploadKind::Multipart
                    && (! filled($upload->provider_upload_id)
                        || ! str_starts_with((string) $upload->storage_key, $this->paths->assetPrefix($asset).'/')))) {
                throw ValidationException::withMessages(['upload' => 'An upload points outside this video storage location and cannot be cleaned up here.']);
            }
        }

        return [
            'bucket' => config("filesystems.disks.{$disk}.bucket"),
            'root' => trim((string) config("filesystems.disks.{$disk}.root", ''), '/'),
            'keys' => $this->keys($disk, $prefix),
            'uploads' => $uploads->map(fn (MediaUpload $upload): array => [
                'uuid' => $upload->uuid,
                'kind' => $upload->kind->value,
                'storage_key' => $upload->storage_key,
                'provider_upload_id' => $upload->provider_upload_id,
            ])->all(),
            'assets' => $assets->sortBy('uuid')->map(fn (MediaAsset $asset): array => [
                'uuid' => $asset->uuid, 'status' => $asset->status->value,
            ])->values()->all(),
            'requires_force' => $assets->contains('status', MediaAssetStatus::Processing) || $uploads->isNotEmpty(),
        ];
    }

    private function archiveAsset(MediaAsset $asset): void
    {
        $asset->update(['is_visible' => false, 'status' => MediaAssetStatus::Archived, 'archived_at' => now()]);
    }

    /** @return list<string> */
    private function keys(string $disk, string $prefix): array
    {
        $keys = array_values(array_filter(
            Storage::disk($disk)->allFiles($prefix),
            fn (string $key): bool => str_starts_with($key, $prefix),
        ));
        sort($keys);

        return $keys;
    }

    private function verify(string $target, array $preview, string $digest, bool $force): void
    {
        if (! hash_equals($this->digest($target, $preview), $digest)) {
            throw ValidationException::withMessages(['confirmation' => 'The files or uploads changed since the confirmation page loaded. Review the list again.']);
        }
        if ($preview['requires_force'] && ! $force) {
            throw ValidationException::withMessages(['force' => 'Confirm force deletion to cancel unfinished uploads and remove this media.']);
        }
    }

    private function cleanup(string $disk, string $prefix, array $preview): void
    {
        try {
            foreach ($preview['uploads'] as $item) {
                if ($item['kind'] === MediaUploadKind::Multipart->value) {
                    try {
                        $this->storage->abortMultipart($disk, $item['storage_key'], $item['provider_upload_id']);
                    } catch (AwsException $exception) {
                        // An earlier abort may have succeeded before its response was lost.
                        if ($exception->getAwsErrorCode() !== 'NoSuchUpload') {
                            throw $exception;
                        }
                    }
                }
                MediaUpload::query()->where('uuid', $item['uuid'])->update([
                    'status' => MediaUploadStatus::Aborted, 'aborted_at' => now(),
                ]);
            }

            if ($this->keys($disk, $prefix) !== $preview['keys']) {
                throw ValidationException::withMessages(['confirmation' => 'The files changed during upload cancellation. Media remains hidden. Review the list again.']);
            }
            foreach ($preview['keys'] as $key) {
                if (! Storage::disk($disk)->delete($key)) {
                    throw new RuntimeException("Could not delete media object {$key}.");
                }
            }
            if ($this->keys($disk, $prefix) !== []) {
                throw ValidationException::withMessages(['confirmation' => 'New files arrived during cleanup. Stop the uploader and review the list again.']);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['confirmation' => 'Storage cleanup failed. Media remains hidden and database records were retained. Review and retry deletion; contact an administrator if it keeps failing.']);
        }
    }
}
