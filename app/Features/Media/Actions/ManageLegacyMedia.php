<?php

namespace App\Features\Media\Actions;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\S3MediaStorage;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaType;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageLegacyMedia
{
    public function __construct(
        private readonly S3MediaStorage $storage,
        private readonly RecordMediaIngestEvent $recordEvent,
    ) {}

    /** @return array<string, mixed> */
    public function list(Concert $concert, ?string $cursor): array
    {
        $disk = (string) config('media.legacy_disk');
        $prefixes = $this->allowedPrefixes($concert, $disk);
        if ($prefixes === []) {
            return ['objects' => [], 'next_cursor' => null, 'configured_prefixes' => 0];
        }

        $position = $this->decodeCursor($concert, $cursor);
        $prefixIndex = $position['prefix_index'];
        $continuation = $position['continuation_token'];
        $pageSize = (int) config('media.legacy_page_size');
        $objects = [];
        $nextCursor = null;
        $pagesScanned = 0;

        while ($prefixIndex < count($prefixes) && $objects === [] && $pagesScanned < 10) {
            $pagesScanned++;
            $page = $this->storage->listObjects($disk, $prefixes[$prefixIndex], $pageSize, $continuation);
            $objects = collect($page['objects'])
                ->filter(fn (array $object): bool => str_starts_with($object['key'], $prefixes[$prefixIndex])
                    && strtolower(pathinfo($object['key'], PATHINFO_EXTENSION)) === 'mp4')
                ->map(fn (array $object): array => [
                    'object_ref' => $this->objectReference($concert, $disk, $object['key']),
                    'filename' => basename($object['key']),
                    'size_bytes' => $object['size'],
                    'last_modified' => $object['last_modified'],
                ])->values()->all();

            if ($page['next_token'] !== null) {
                $nextCursor = $this->cursor($concert, $prefixIndex, $page['next_token']);
                $continuation = $page['next_token'];
            } elseif ($prefixIndex + 1 < count($prefixes)) {
                $nextCursor = $this->cursor($concert, $prefixIndex + 1, null);
                $prefixIndex++;
                $continuation = null;
            } else {
                $nextCursor = null;
                $prefixIndex++;
            }
        }

        return ['objects' => $objects, 'next_cursor' => $nextCursor, 'configured_prefixes' => count($prefixes)];
    }

    /**
     * @param  array{object_ref: string, display_name: string, sort_order?: int|null, is_visible?: bool|null}  $data
     */
    public function import(MediaCollection $collection, User $user, array $data, string $idempotencyKey): MediaAsset
    {
        if (! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'A UUID Idempotency-Key header is required.']);
        }
        $existing = MediaAsset::query()->where('created_by_user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->media_collection_id !== $collection->id) {
                throw ValidationException::withMessages(['Idempotency-Key' => 'This idempotency key belongs to another collection.']);
            }

            return $existing;
        }

        $collection->loadMissing('concert');
        $reference = $this->decodeReference($collection->concert, $data['object_ref']);
        $allowed = collect($this->allowedPrefixes($collection->concert, $reference['disk']))
            ->contains(fn (string $prefix): bool => str_starts_with($reference['key'], rtrim($prefix, '/').'/'));
        if (! $allowed || strtolower(pathinfo($reference['key'], PATHINFO_EXTENSION)) !== 'mp4') {
            throw ValidationException::withMessages(['object_ref' => 'The legacy object is outside this concert or is not an MP4.']);
        }

        $head = $this->storage->head($reference['disk'], $reference['key']);
        if ($head === null || $head['size'] < 1) {
            throw ValidationException::withMessages(['object_ref' => 'The legacy media object no longer exists.']);
        }

        return DB::transaction(function () use ($collection, $user, $data, $idempotencyKey, $reference, $head): MediaAsset {
            $asset = MediaAsset::query()->create([
                'media_collection_id' => $collection->id,
                'media_type' => MediaType::Video,
                'storage_disk' => $reference['disk'],
                'storage_key' => $reference['key'],
                'original_filename' => basename($reference['key']),
                'display_name' => $data['display_name'],
                'status' => MediaAssetStatus::Available,
                'is_visible' => false,
                'sort_order' => $data['sort_order'] ?? 0,
                'size_bytes' => $head['size'],
                'mime_type' => $head['content_type'] ?: 'video/mp4',
                'extension' => 'mp4',
                'created_by_user_id' => $user->id,
                'idempotency_key' => $idempotencyKey,
                'verified_at' => now(),
                'metadata' => ['legacy_import' => ['imported_at' => now()->toISOString()]],
            ]);
            $this->recordEvent->handle($user, $asset, 'media_asset.legacy_imported', ['collection_uuid' => $collection->uuid]);

            return $asset;
        });
    }

    /** @return list<string> */
    private function allowedPrefixes(Concert $concert, string $disk): array
    {
        $prefixes = $concert->mediaCollections()
            ->where('storage_disk', $disk)
            ->pluck('storage_prefix')
            ->filter()
            ->map(fn (string $prefix): string => trim($prefix, '/'))
            ->all();

        if ($concert->storage_disk === $disk && filled($concert->storage_prefix)) {
            $prefixes[] = trim($concert->storage_prefix, '/');
        }

        return array_values(array_unique(array_map(
            fn (string $prefix): string => $prefix.'/',
            array_filter($prefixes, fn (string $prefix): bool => $prefix !== ''),
        )));
    }

    /** @return array{prefix_index: int, continuation_token: string|null} */
    private function decodeCursor(Concert $concert, ?string $cursor): array
    {
        if ($cursor === null) {
            return ['prefix_index' => 0, 'continuation_token' => null];
        }

        try {
            $data = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages(['cursor' => 'The legacy media cursor is invalid.']);
        }
        if (($data['concert_uuid'] ?? null) !== $concert->uuid || ($data['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['cursor' => 'The legacy media cursor is invalid or expired.']);
        }

        return ['prefix_index' => (int) ($data['prefix_index'] ?? 0), 'continuation_token' => $data['continuation_token'] ?? null];
    }

    private function cursor(Concert $concert, int $prefixIndex, ?string $continuationToken): string
    {
        return Crypt::encryptString(json_encode([
            'concert_uuid' => $concert->uuid,
            'prefix_index' => $prefixIndex,
            'continuation_token' => $continuationToken,
            'expires_at' => now()->addMinutes((int) config('media.object_ref_ttl_minutes'))->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    private function objectReference(Concert $concert, string $disk, string $key): string
    {
        return Crypt::encryptString(json_encode([
            'concert_uuid' => $concert->uuid,
            'disk' => $disk,
            'key' => $key,
            'expires_at' => now()->addMinutes((int) config('media.object_ref_ttl_minutes'))->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{disk: string, key: string} */
    private function decodeReference(Concert $concert, string $reference): array
    {
        try {
            $data = json_decode(Crypt::decryptString($reference), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages(['object_ref' => 'The legacy object reference is invalid.']);
        }
        if (($data['concert_uuid'] ?? null) !== $concert->uuid
            || ($data['disk'] ?? null) !== config('media.legacy_disk')
            || ($data['expires_at'] ?? 0) < now()->timestamp
            || ! is_string($data['key'] ?? null)) {
            throw ValidationException::withMessages(['object_ref' => 'The legacy object reference is invalid or expired.']);
        }

        return ['disk' => $data['disk'], 'key' => $data['key']];
    }
}
