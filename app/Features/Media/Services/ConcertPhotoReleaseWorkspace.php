<?php

namespace App\Features\Media\Services;

use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Media\Models\MediaWatermark;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Services\ConcertPhotoUploadStatus;

class ConcertPhotoReleaseWorkspace
{
    public function forEvent(SchedulingEvent $event): array
    {
        if (! $event->studio_id) {
            return ['releaseEvents' => collect(), 'groups' => collect(), 'watermarks' => collect(), 'releaseBlockers' => [], 'defaultReleaseName' => 'Studio photos'];
        }
        $events = SchedulingEvent::where('studio_id', $event->studio_id)->whereYear('event_date', $event->event_date->year)
            ->whereNull('cancelled_at')->where('event_type', 'concert')
            ->whereHas('concertBookingItem', fn ($query) => $query->whereNull('cancelled_at')
                ->where(fn ($query) => $query->whereHas('concert.mediaCollections', fn ($albums) => $albums->where('media_type', 'photo'))
                    ->orWhere(fn ($q) => $q->where('item_type', 'concert')->whereHas('booking', fn ($b) => $b->where('wants_concert_photography', true)))
                    ->orWhere(fn ($q) => $q->where('item_type', 'dress_rehearsal')->whereHas('booking', fn ($b) => $b->where('wants_portrait_photography', true)))))
            ->with(['studio', 'concertBookingItem.concert.mediaCollections' => fn ($query) => $query->where('media_type', 'photo')
                ->with('concertPhotoReleaseGroups', 'coverAsset')->withCount(['assets as photo_count' => fn ($assets) => $assets->where('media_type', 'photo')->where('status', 'available')->where('is_visible', true)])])
            ->orderBy('event_date')->orderBy('id')->get();
        $releaseEvents = $events->map(function ($session) {
            $concert = $session->concertBookingItem->concert;
            $albums = $concert?->mediaCollections ?? collect();
            $released = $albums->isNotEmpty() && $albums->every(fn ($album) => $album->status->value === 'published' && $album->published_at);
            $grouped = $albums->contains(fn ($album) => $album->concertPhotoReleaseGroups->isNotEmpty());
            $upload = app(ConcertPhotoUploadStatus::class)->forEvent($session);
            $reason = match (true) {
                $released => 'Photos are available to registered studio accounts.',
                $grouped => 'Included in a prepared release below.',
                $session->concertBookingItem->approval_status !== 'approved' => 'Approve this event before releasing its photos.',
                $albums->contains(fn ($album) => $album->status->value !== 'draft' || $album->visibility->value !== 'private' || $album->published_at) => 'Some albums are unavailable or already published. Review them before release.',
                $upload['key'] !== 'complete' => 'Waiting for all required photo roles to finish uploading.',
                $albums->isEmpty() || $albums->contains(fn ($album) => ! $album->photo_count) => 'Every album needs available photos before release.',
                default => null,
            };
            $cover = null;
            foreach ($albums as $album) {
                $cover = $album->galleryFolderCoverAsset();
                if ($cover) {
                    break;
                }
            }

            return ['event' => $session, 'title' => $concert?->photoGalleryTitle() ?? $session->concertBookingItem->title ?? $session->name,
                'photos' => $albums->sum('photo_count'), 'albums' => $albums->count(), 'cover' => $cover,
                'ready' => $reason === null, 'released' => $released, 'grouped' => $grouped, 'reason' => $reason,
                'label' => $released ? 'Released' : ($grouped ? 'Prepared release' : ($reason === null ? 'Ready to release' : 'Waiting for photos'))];
        });
        $groups = ConcertPhotoReleaseGroup::with('studio', 'watermark', 'collections.concert.bookingItem.schedulingEvent')
            ->where('studio_id', $event->studio_id)
            ->whereHas('collections.concert.bookingItem.schedulingEvent', fn ($query) => $query->whereYear('event_date', $event->event_date->year))
            ->latest()->get();
        $releaseBlockers = $groups->mapWithKeys(fn ($group) => [$group->id => $group->released_at ? [] : app(ConcertPhotoReleases::class)->releaseBlockers($group)]);
        $watermarks = MediaWatermark::where('gallery_type', 'concert')->where('is_active', true)->orderBy('name')->get();
        $defaultReleaseName = $event->studio->name.' '.$event->event_date->year.' photos';

        return compact('releaseEvents', 'groups', 'watermarks', 'releaseBlockers', 'defaultReleaseName');
    }
}
