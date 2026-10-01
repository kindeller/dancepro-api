<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Families\Services\FamilyGalleryAccess;
use App\Features\Programmes\Services\CompetitionGallerySettings;
use App\Models\User;
use Illuminate\Support\Collection;

class CustomerEvents
{
    public function __construct(private readonly CustomerConcertAccess $concertAccess, private readonly CompetitionGallerySettings $gallerySettings, private readonly CustomerConcertGallery $concertGallery) {}

    public function forUser(User $user, Collection $verifiedStudios, array $purchases): array
    {
        $events = [];
        $registrations = app(FamilyGalleryAccess::class)->competitions($user)->whereNotNull('access_verified_at')
            ->whereHas('event', fn ($query) => $query->where('event_type', 'competition')->whereNull('cancelled_at'))
            ->with('event.competitionContact')->get();
        $competitionEvents = $registrations->pluck('event');
        $titles = $this->gallerySettings->forEvents($competitionEvents);

        foreach ($competitionEvents as $event) {
            $key = 'competition:'.$event->id;
            $events[$key] = ['event' => $event, 'kind' => 'competition', 'title' => $titles[$event->uuid]['title'],
                'gallery' => true, 'photos' => false, 'video' => false, 'purchased' => false];
        }

        $studioConcerts = Concert::with('studio', 'bookingItem')->whereIn('studio_id', $verifiedStudios->pluck('id'))
            ->whereIn('status', ['draft', 'published'])->get()
            ->filter(fn (Concert $concert) => $concert->isPhotoGalleryAvailable()
                && ($concert->galleryForDisplay() || $this->concertGallery->hasNativeAlbums($concert)));
        foreach ($studioConcerts as $concert) {
            $key = 'concert:'.$concert->id;
            $events[$key] = $this->concertCard($concert);
            $events[$key]['photos'] = true;
            $events[$key]['native_photos'] = $this->concertGallery->hasNativeAlbums($concert);
        }

        $grants = $this->concertAccess->grants($user)->with('concert.studio', 'concert.bookingItem')->get();
        foreach ($grants as $grant) {
            $concert = $grant->concert;
            if (! $concert || ! $concert->isPubliclyAvailable() || ! $this->concertAccess->matches($grant, $concert)) {
                continue;
            }
            $key = 'concert:'.$concert->id;
            $events[$key] ??= $this->concertCard($concert);
            $events[$key]['video'] = true;
        }

        foreach ([...$purchases['photos'], ...$purchases['videos']] as $purchase) {
            $event = $purchase['event'];
            $key = $purchase['kind'].':'.$event->id;
            $events[$key] ??= $purchase['kind'] === 'concert' ? $this->concertCard($event)
                : ['event' => $event, 'kind' => 'competition', 'title' => $event->name,
                    'gallery' => $purchase['can_browse'], 'photos' => false, 'video' => false, 'purchased' => false];
            $events[$key]['purchased'] = true;
        }

        return collect($events)->sortByDesc(fn ($entry) => $entry['event']->event_date?->format('Y-m-d') ?? '')->values()->all();
    }

    private function concertCard(Concert $concert): array
    {
        $cover = $this->concertGallery->parentCoverAsset($concert);

        return ['event' => $concert, 'kind' => 'concert', 'title' => $concert->name,
            'gallery' => false, 'photos' => false, 'native_photos' => false, 'video' => false, 'purchased' => false,
            'thumbnail_url' => $cover ? route('customer.concerts.photos.image', [
                'concert' => $concert, 'asset' => $cover->uuid, 'size' => 'cover',
            ]) : null];
    }
}
