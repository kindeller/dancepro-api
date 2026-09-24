<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerConcertGallery
{
    public function allows(User $user, Concert $concert): bool
    {
        return $user->canAccessCustomer() && $concert->isPubliclyAvailable()
            && CustomerStudio::where('user_id', $user->id)->where('studio_id', $concert->studio_id)
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
        abort_unless($concert->isPubliclyAvailable(), 404);
        $groups = $this->releaseGroups($concert)->get();
        $selectedGroup = $group ? $groups->firstWhere('uuid', $group) : null;
        abort_if($group && ! $selectedGroup, 404);
        $collections = $selectedGroup
            ? $selectedGroup->collections->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values()
            : $this->availableCollections($concert, $groups);
        $selected = $folder ? $collections->firstWhere('uuid', $folder) : null;
        if ($folder && ! $selected && ! $selectedGroup) {
            $selected = $this->availableCollections($concert, $groups)->firstWhere('uuid', $folder);
            $selectedGroup = $selected?->concertPhotoReleaseGroups->first();
            $collections = $selectedGroup?->collections?->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values() ?? $collections;
        }
        abort_if($folder && ! $selected, 404);
        $photos = $selected ? $selected->assets()->where('media_type', 'photo')->where('status', 'available')
            ->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->paginate(48)->withQueryString() : null;

        return compact('concert', 'groups', 'selectedGroup', 'collections', 'selected', 'photos');
    }

    public function image(User $user, Concert $concert, string $assetUuid, string $size, ConcertPhotoPreview $preview): StreamedResponse
    {
        abort_unless($this->allows($user, $concert), 403);

        return $this->imageReleased($concert, $assetUuid, $size, $preview);
    }

    public function imageReleased(Concert $concert, string $assetUuid, string $size, ConcertPhotoPreview $preview): StreamedResponse
    {
        abort_unless($concert->isPubliclyAvailable(), 404);
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
        $groups = $this->releaseGroups($concert)->get();
        $group = $requestedGroup ? $groups->firstWhere('id', $requestedGroup->id) : $groups->first();
        abort_if($requestedGroup && ! $group, 404);

        return $group?->displayCoverAsset();
    }

    public function publicCover(Concert $concert, ConcertPhotoReleaseGroup $group, ConcertPhotoPreview $preview): StreamedResponse
    {
        abort_unless($concert->isPubliclyAvailable(), 404);
        $asset = $this->parentCoverAsset($concert, $group);
        abort_unless($asset, 404);
        $key = $preview->key($asset, 'thumbnail');

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
