<?php

namespace App\Features\Media\Actions;

use App\Features\Concerts\Support\ConcertMediaPath;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaCollectionVisibility;
use App\Features\Media\Support\MediaUploadDestination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DeleteManagedConcertMedia
{
    public function __construct(
        private readonly ConcertMediaPath $paths,
        private readonly MediaUploadDestination $destination,
    ) {}

    /** @return list<string> */
    public function keysForAsset(MediaAsset $asset): array
    {
        $this->ensureAssetAllowed($asset);

        $prefix = $this->paths->assetPrefix($asset).'/';

        return $this->keys($asset->storage_disk, $prefix);
    }

    /** @return list<string> */
    public function keysForCollection(MediaCollection $collection): array
    {
        $this->destination->ensureCollectionAllowed($collection);
        foreach ($collection->assets as $asset) {
            $this->ensureAssetAllowed($asset);
        }

        return $this->keys($collection->storage_disk, trim($collection->uuid, '/').'/');
    }

    /** @param list<string> $keys */
    public function digest(string $target, array $keys): string
    {
        return hash_hmac('sha256', json_encode([$target, $keys], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function deleteAsset(MediaAsset $asset, string $digest): void
    {
        $keys = $this->keysForAsset($asset);
        $this->verify($asset->storage_disk.':'.$asset->uuid, $keys, $digest);
        $asset->update(['is_visible' => false]);
        $this->deleteKeys($asset->storage_disk, $keys);
        $asset->delete();
    }

    public function deleteCollection(MediaCollection $collection, string $digest): void
    {
        $keys = $this->keysForCollection($collection);
        $this->verify($collection->storage_disk.':'.$collection->uuid, $keys, $digest);
        $collection->update([
            'status' => MediaCollectionStatus::Draft,
            'visibility' => MediaCollectionVisibility::Private,
            'published_at' => null,
        ]);
        $this->deleteKeys($collection->storage_disk, $keys);
        DB::transaction(function () use ($collection): void {
            foreach ($collection->assets as $asset) {
                $asset->delete();
            }
            $collection->delete();
        });
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

    private function ensureAssetAllowed(MediaAsset $asset): void
    {
        $this->destination->ensureCollectionAllowed($asset->collection);
        if ($asset->storage_disk !== $asset->collection->storage_disk) {
            throw ValidationException::withMessages(['asset' => 'This video uses a different storage disk and cannot be deleted here.']);
        }
        if ($asset->status === MediaAssetStatus::Processing) {
            throw ValidationException::withMessages(['asset' => 'Finish or cancel this upload before deleting it.']);
        }
    }

    /** @param list<string> $keys */
    private function verify(string $target, array $keys, string $digest): void
    {
        if (! hash_equals($this->digest($target, $keys), $digest)) {
            throw ValidationException::withMessages(['confirmation' => 'The files changed since the confirmation page loaded. Review the list again.']);
        }
    }

    /** @param list<string> $keys */
    private function deleteKeys(string $disk, array $keys): void
    {
        foreach ($keys as $key) {
            if (! Storage::disk($disk)->delete($key)) {
                throw new RuntimeException("Could not delete media object {$key}; database records were retained.");
            }
        }
    }
}
