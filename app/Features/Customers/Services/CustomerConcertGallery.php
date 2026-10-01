<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Families\Services\FamilyGalleryAccess;
use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\AdminTopPics;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerConcertGallery
{
    public function allows(User $user, Concert $concert): bool
    {
        return $user->canAccessCustomer() && $concert->isPhotoGalleryAvailable()
            && app(FamilyGalleryAccess::class)->studios($user)->where('studio_id', $concert->studio_id)
                ->whereNotNull('access_verified_at')->exists();
    }

    public function hasNativeAlbums(Concert $concert): bool
    {
        return $this->collections($concert)->exists();
    }

    public function browse(User $user, Concert $concert, ?string $folder, ?string $group = null): array
    {
        abort_unless($this->allows($user, $concert), 403);

        return $this->browseReleased($concert, $folder, $group);
    }

    public function browseReleased(Concert $concert, ?string $folder, ?string $group = null): array
    {
        abort_unless($concert->isPhotoGalleryAvailable(), 404);
        $groups = $this->releaseGroups($concert)->get();
        $selectedGroup = $group ? $groups->firstWhere('uuid', $group) : null;
        abort_if($group && ! $selectedGroup, 404);
        $collections = $selectedGroup
            ? $selectedGroup->collections->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values()
            : $this->collections($concert)->with('coverAsset', 'concertPhotoReleaseGroups', 'concert')->get();
        $selected = $folder ? $collections->firstWhere('uuid', $folder) : null;
        if ($folder && ! $selected && ! $selectedGroup) {
            $selected = $this->availableCollections($concert, $groups)->firstWhere('uuid', $folder);
            $selectedGroup = $selected?->concertPhotoReleaseGroups->first();
            $collections = $selectedGroup?->collections?->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values() ?? $collections;
        }
        $collections->loadCount(['assets as visible_photo_count' => fn ($query) => $query
            ->where('media_type', 'photo')->where('status', 'available')->where('is_visible', true)]);
        abort_if($folder && ! $selected, 404);
        $flatPortraitGallery = $concert->isDressRehearsal() && (! $selected || $selected->concert_id === $concert->id);
        if ($flatPortraitGallery && ! $folder) {
            // Skip release-group and album navigation for this one-folder session.
            $portraits = $collections->where('concert_id', $concert->id);
            if ($portraits->count() === 1) {
                $selected = $portraits->first();
            } else {
                $flatPortraitGallery = false;
            }
        }
        $photos = $selected ? $selected->assets()->where('media_type', 'photo')->where('status', 'available')
            ->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->paginate(48)->withQueryString() : null;

        return compact('concert', 'groups', 'selectedGroup', 'collections', 'selected', 'photos', 'flatPortraitGallery');
    }

    public function image(User $user, Concert $concert, string $assetUuid, string $size, ConcertPhotoPreview $preview): StreamedResponse
    {
        abort_unless($this->allows($user, $concert), 403);

        return $this->imageReleased($concert, $assetUuid, $size, $preview);
    }

    public function imageReleased(Concert $concert, string $assetUuid, string $size, ConcertPhotoPreview $preview): StreamedResponse
    {
        abort_unless($concert->isPhotoGalleryAvailable(), 404);
        $asset = $this->assetForReleasedConcert($concert, $assetUuid);
        $key = $preview->key($asset, $size);

        return Storage::disk('local')->response($key, $asset->uuid.'-'.$size.'.jpg', [
            'Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function assetForReleasedConcert(Concert $concert, string $assetUuid): MediaAsset
    {
        return MediaAsset::where('uuid', $assetUuid)->where('media_type', 'photo')->where('status', 'available')
            ->where('is_visible', true)->whereIn('media_collection_id', $this->availableCollectionIds($concert))
            ->with('collection.concert')->firstOrFail();
    }

    public function parentCoverAsset(Concert $concert, ?ConcertPhotoReleaseGroup $requestedGroup = null): ?MediaAsset
    {
        abort_if($requestedGroup && ! $this->releaseGroups($concert)->whereKey($requestedGroup->id)->exists(), 404);

        // Release groups batch announcements; event cards must keep their own cover.
        $collections = $this->collections($concert)
            ->when($requestedGroup, fn ($query) => $query->whereHas('concertPhotoReleaseGroups',
                fn ($groups) => $groups->whereKey($requestedGroup->id)))->pluck('id');
        $assets = MediaAsset::query()->whereIn('media_collection_id', $collections)
            ->where('media_type', 'photo')->where('status', 'available')->where('is_visible', true)
            ->where('metadata->is_top_pic', true);
        $eventId = $concert->bookingItem?->scheduling_event_id;
        $coverUuid = $eventId ? app(AdminTopPics::class)->counts()
            ->where('galleries.scheduling_event_id', $eventId)->first()?->cover_uuid : null;

        // Respect the same chosen Top Pic (or automatic default) used by admin.
        return ($coverUuid ? (clone $assets)->where('metadata->programme_photo_file_uuid', $coverUuid)->first() : null)
            ?? $assets->orderBy('metadata->programme_photo_file_uuid')->orderBy('id')->first();
    }

    public function publicCover(Concert $concert, ConcertPhotoReleaseGroup $group, ConcertPhotoPreview $preview): StreamedResponse
    {
        abort_unless($concert->isPhotoGalleryAvailable(), 404);
        $asset = $this->parentCoverAsset($concert, $group);
        abort_unless($asset, 404);
        $key = $preview->key($asset, 'cover');

        return Storage::disk('local')->response($key, $group->uuid.'-cover.jpg', [
            'Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function collections(Concert $concert)
    {
        return MediaCollection::where('concert_id', $concert->id)->where('media_type', 'photo')
            ->where('status', 'published')->whereIn('visibility', ['customer', 'public'])->whereNotNull('published_at')
            ->orderBy('sort_order')->orderBy('id');
    }

    private function releaseGroups(Concert $concert)
    {
        return ConcertPhotoReleaseGroup::query()->where('studio_id', $concert->studio_id)->whereNotNull('released_at')
            ->whereHas('collections', fn ($query) => $query->where('concert_id', $concert->id)
                ->where('media_type', 'photo')->where('status', 'published')
                ->whereIn('visibility', ['customer', 'public'])->whereNotNull('published_at'))
            ->with('coverAsset', 'collections.coverAsset', 'collections.concert', 'collections.concertPhotoReleaseGroups')
            ->orderBy('id');
    }

    private function availableCollections(Concert $concert, $groups)
    {
        $grouped = $groups->flatMap->collections;
        $direct = $this->collections($concert)->with('coverAsset', 'concertPhotoReleaseGroups.collections', 'concert')->get();

        return $grouped->concat($direct)->unique('id')->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
    }

    private function availableCollectionIds(Concert $concert): array
    {
        $groups = $this->releaseGroups($concert)->get();

        return $this->availableCollections($concert, $groups)->pluck('id')->all();
    }
}
