<?php

namespace App\Features\Media\Services;

use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaWatermark;
use App\Features\Tracking\Services\ConcertPhotoUploadStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConcertPhotoReleases
{
    public function create(User $actor, array $input): ConcertPhotoReleaseGroup
    {
        $collections = MediaCollection::query()->whereIn('uuid', $input['collection_uuids'])
            ->where('media_type', 'photo')->where('status', 'draft')->where('visibility', 'private')
            ->whereNull('published_at')->whereDoesntHave('concertPhotoReleaseGroups')->with('concert')->get();
        abort_unless($collections->count() === count($input['collection_uuids']), 422, 'Choose valid concert photo albums.');
        $studioIds = $collections->pluck('concert.studio_id')->filter()->unique();
        abort_unless($studioIds->count() === 1, 422, 'A release group can contain albums from one studio only.');
        if ($collections->contains(fn (MediaCollection $collection) => $collection->published_at !== null)) {
            throw ValidationException::withMessages(['collection_uuids' => 'Choose albums that have not already been released.']);
        }
        $watermark = $this->watermark($input['watermark_uuid'] ?? null);

        return DB::transaction(function () use ($actor, $input, $collections, $studioIds, $watermark): ConcertPhotoReleaseGroup {
            $group = ConcertPhotoReleaseGroup::create([
                'studio_id' => $studioIds->first(), 'name' => $input['name'],
                'media_watermark_id' => $watermark?->id, 'created_by_user_id' => $actor->id,
            ]);
            $group->collections()->attach($collections->pluck('id'));
            $defaultCover = MediaAsset::query()->whereIn('media_collection_id', $collections->pluck('id'))
                ->where('media_type', 'photo')->where('status', 'available')->where('is_visible', true)
                ->where('metadata->is_top_pic', true)->orderBy('sort_order')->orderBy('id')->first();
            if ($defaultCover) {
                $group->update(['cover_media_asset_id' => $defaultCover->id]);
            }
            $this->record($group, $actor, 'created', ['collection_uuids' => $collections->pluck('uuid')->all()]);

            return $group->load('collections.concert', 'watermark');
        });
    }

    public function updateGroupCover(ConcertPhotoReleaseGroup $group, User $actor, ?string $assetUuid): void
    {
        $candidates = MediaAsset::query()->where('media_type', 'photo')->where('status', 'available')->where('is_visible', true)
            ->whereIn('media_collection_id', $group->collections()->pluck('media_collections.id'))
            ->where('metadata->is_top_pic', true)->orderBy('sort_order')->orderBy('id')->get();
        $asset = $assetUuid ? $candidates->firstWhere('uuid', $assetUuid) : $candidates->first();
        if ($assetUuid && ! $asset) {
            throw ValidationException::withMessages(['asset_uuid' => 'Choose a Top Pic from this parent album.']);
        }
        $group->update(['cover_media_asset_id' => $asset?->id]);
        $this->record($group, $actor, 'cover_updated', ['asset_uuid' => $asset?->uuid]);
    }

    public function updateAlbumCover(MediaCollection $collection, User $actor, ?string $assetUuid): void
    {
        abort_unless($collection->media_type->value === 'photo' && $collection->concert_id !== null, 404);
        $query = $collection->assets()->where('media_type', 'photo')->where('status', 'available')->where('is_visible', true);
        $asset = $assetUuid ? (clone $query)->where('uuid', $assetUuid)->firstOrFail()
            : (clone $query)->orderBy('sort_order')->orderBy('id')->first();
        $collection->update(['cover_media_asset_id' => $asset?->id]);
        foreach ($collection->concertPhotoReleaseGroups as $group) {
            $this->record($group, $actor, 'album_cover_updated', [
                'collection_uuid' => $collection->uuid,
                'asset_uuid' => $asset?->uuid,
            ]);
        }
    }

    public function release(ConcertPhotoReleaseGroup $group, User $actor): void
    {
        DB::transaction(function () use ($group, $actor): void {
            $group = ConcertPhotoReleaseGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $group->setRelation('collections', $group->collections()->with('concert.bookingItem.schedulingEvent')->lockForUpdate()->get());
            $blockers = $this->releaseBlockers($group);
            if ($blockers) {
                throw ValidationException::withMessages(['release' => implode(' ', $blockers)]);
            }
            $collections = $group->collections;
            foreach ($collections as $collection) {
                $collection->update(['status' => 'published', 'visibility' => 'customer',
                    'published_at' => now(), 'media_watermark_id' => $group->media_watermark_id]);
            }
            $group->update(['released_at' => now(), 'released_by_user_id' => $actor->id]);
            $this->record($group, $actor, 'released', ['collection_uuids' => $collections->pluck('uuid')->all()]);
        });
    }

    public function releaseBlockers(ConcertPhotoReleaseGroup $group): array
    {
        $group->loadMissing('collections.concert.bookingItem.schedulingEvent');
        if ($group->released_at) {
            return ['This photo group has already been released.'];
        }
        if ($group->collections->isEmpty()) {
            return ['This release group has no albums.'];
        }
        $blockers = [];
        $checkedEvents = [];
        foreach ($group->collections as $collection) {
            if ($collection->media_type->value !== 'photo' || $collection->concert?->studio_id !== $group->studio_id) {
                $blockers[] = $collection->name.': the album must contain photos for this studio.';
            }
            $event = $collection->concert?->bookingItem?->schedulingEvent;
            if ($event && ! isset($checkedEvents[$event->id])) {
                $checkedEvents[$event->id] = true;
                if ($event->cancelled_at) {
                    $blockers[] = $event->name.': this event has been cancelled.';
                } elseif (app(ConcertPhotoUploadStatus::class)->forEvent($event)['key'] !== 'complete') {
                    $blockers[] = $event->name.': all required photographers must confirm Finished upload before this gallery can be released.';
                }
            }
            if (! $collection->assets()->where('media_type', 'photo')->where('status', 'available')->where('is_visible', true)->exists()) {
                $blockers[] = $collection->name.' has no available visible photos.';
            }
        }

        return $blockers;
    }

    private function watermark(?string $uuid): ?MediaWatermark
    {
        if (! $uuid) {
            return null;
        }

        return MediaWatermark::where('uuid', $uuid)->where('gallery_type', 'concert')->where('is_active', true)->firstOrFail();
    }

    private function record(ConcertPhotoReleaseGroup $group, User $actor, string $action, array $details): void
    {
        DB::table('concert_photo_release_group_changes')->insert([
            'concert_photo_release_group_id' => $group->id, 'user_id' => $actor->id,
            'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
