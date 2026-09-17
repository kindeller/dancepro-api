<?php

namespace App\Features\Media\Actions;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Support\ConcertMediaPath;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaCatalogueMode;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaCollectionVisibility;
use App\Features\Media\Support\MediaType;
use App\Features\Media\Support\MediaUploadDestination;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageMediaCatalogue
{
    public function __construct(
        private readonly ConcertMediaPath $mediaPath,
        private readonly RecordMediaIngestEvent $recordEvent,
        private readonly MediaUploadDestination $destination,
    ) {}

    /**
     * @param  array{name: string, media_type: string, sort_order?: int|null}  $data
     */
    public function createCollection(Concert $concert, User $user, array $data, string $idempotencyKey): MediaCollection
    {
        $this->destination->ensureAllowed((string) config('media.upload_disk'));
        $this->validateIdempotencyKey($idempotencyKey);

        $existing = MediaCollection::query()
            ->where('created_by_user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            if ($existing->concert_id !== $concert->id) {
                throw ValidationException::withMessages(['Idempotency-Key' => 'This idempotency key belongs to another concert.']);
            }

            return $existing;
        }

        return DB::transaction(function () use ($concert, $user, $data, $idempotencyKey): MediaCollection {
            $uuid = (string) Str::uuid();
            $collection = MediaCollection::query()->create([
                'uuid' => $uuid,
                'concert_id' => $concert->id,
                'name' => $data['name'],
                'media_type' => MediaType::Video,
                'catalogue_mode' => MediaCatalogueMode::Managed,
                'status' => MediaCollectionStatus::Draft,
                'visibility' => MediaCollectionVisibility::Private,
                'storage_disk' => config('media.upload_disk'),
                'storage_prefix' => $uuid.'/',
                'created_by_user_id' => $user->id,
                'idempotency_key' => $idempotencyKey,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->recordEvent->handle($user, null, 'media_collection.created', ['collection_uuid' => $collection->uuid, 'concert_uuid' => $concert->uuid]);

            return $collection;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCollection(MediaCollection $collection, array $data): MediaCollection
    {
        if (($data['status'] ?? null) === MediaCollectionStatus::Published->value) {
            $this->destination->ensureCollectionAllowed($collection);
        }

        if (($data['status'] ?? null) === MediaCollectionStatus::Published->value
            && ! $collection->assets()->where('status', MediaAssetStatus::Available->value)
                ->where('is_visible', true)->whereNotNull('verified_at')->exists()) {
            throw ValidationException::withMessages(['status' => 'Publish a verified, visible video before publishing the collection.']);
        }

        if (($data['status'] ?? null) === MediaCollectionStatus::Published->value) {
            $data['visibility'] = MediaCollectionVisibility::Public;
            $data['published_at'] = $collection->published_at ?? now();
        } elseif (($data['status'] ?? null) === MediaCollectionStatus::Draft->value) {
            $data['visibility'] = MediaCollectionVisibility::Private;
            $data['published_at'] = null;
        }

        $collection->update($data);

        return $collection->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reserveAsset(MediaCollection $collection, User $user, array $data, string $idempotencyKey): MediaAsset
    {
        $this->destination->ensureCollectionAllowed($collection);
        $this->validateIdempotencyKey($idempotencyKey);

        $existing = MediaAsset::query()
            ->where('created_by_user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            if ($existing->media_collection_id !== $collection->id) {
                throw ValidationException::withMessages(['Idempotency-Key' => 'This idempotency key belongs to another collection.']);
            }

            return $existing;
        }

        return DB::transaction(function () use ($collection, $user, $data, $idempotencyKey): MediaAsset {
            $asset = new MediaAsset([
                'uuid' => (string) Str::uuid(),
                'media_collection_id' => $collection->id,
                'media_type' => MediaType::Video,
                'storage_disk' => $collection->storage_disk,
                'storage_key' => 'reserved',
                'original_filename' => $data['original_filename'],
                'display_name' => $data['display_name'],
                'status' => MediaAssetStatus::Processing,
                'is_visible' => false,
                'sort_order' => $data['sort_order'] ?? 0,
                'duration_seconds' => data_get($data, 'source.duration_seconds'),
                'extension' => 'mp4',
                'created_by_user_id' => $user->id,
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'ingest' => [
                        'expected_outputs' => array_values($data['expected_outputs']),
                        'source' => $data['source'] ?? [],
                    ],
                ],
            ]);
            $asset->storage_key = $this->mediaPath->canonicalOriginal($asset);
            $asset->save();

            $this->recordEvent->handle($user, $asset, 'media_asset.reserved', ['collection_uuid' => $collection->uuid]);

            return $asset;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAsset(MediaAsset $asset, User $user, array $data): MediaAsset
    {
        if (($data['is_visible'] ?? false) && ($asset->status !== MediaAssetStatus::Available || $asset->verified_at === null)) {
            throw ValidationException::withMessages(['is_visible' => 'Only an available, verified asset can be made visible.']);
        }

        $asset->update($data);
        $this->recordEvent->handle($user, $asset, 'media_asset.updated', ['fields' => array_keys($data)]);

        return $asset->refresh();
    }

    private function validateIdempotencyKey(string $key): void
    {
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'A UUID Idempotency-Key header is required.']);
        }
    }
}
