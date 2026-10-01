<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Programmes\Services\CompetitionGallerySettings;
use App\Features\Programmes\Services\CompetitionSectionReadiness;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use Illuminate\Support\Collection;

class GuestGalleryCatalogue
{
    public function __construct(
        private readonly CompetitionSectionReadiness $readiness,
        private readonly CompetitionGallerySettings $settings,
    ) {}

    public function cards(string $type): Collection
    {
        return match ($type) {
            'concert' => $this->concerts(),
            // Portfolio is reserved; publishing and browsing its media is deferred.
            'portfolio' => collect(),
            default => $this->competitions(),
        };
    }

    public function availableTypes(): array
    {
        $types = ['competition' => 'Competitions', 'concert' => 'Concerts', 'portfolio' => 'Portfolio'];
        if ($this->cards('portfolio')->isEmpty()) {
            unset($types['portfolio']);
        }

        return $types;
    }

    public function competitionIsReleased(SchedulingEvent $event): bool
    {
        if ($event->event_type->value !== 'competition' || $event->cancelled_at !== null) {
            return false;
        }
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $event->id)->first();
        $plan = $gallery ? ProgrammeUploadPlan::find($gallery->programme_upload_plan_id) : null;

        return $plan && collect($this->readiness->forPlan($event, $plan))->contains(fn (array $section): bool => $section['ready']);
    }

    public function concertIsReleased(Concert $concert): bool
    {
        return $concert->isPhotoGalleryAvailable() && $concert->mediaCollections()->where('media_type', 'photo')
            ->where('status', 'published')->whereIn('visibility', ['customer', 'public'])
            ->whereNotNull('published_at')->exists();
    }

    public function eventIsReleased(SchedulingEvent $event): bool
    {
        if ($event->event_type->value === 'competition') {
            return $this->competitionIsReleased($event);
        }

        $event->loadMissing('concertBookingItem.concert.bookingItem');

        return $event->concertBookingItem?->concert
            ? $this->concertIsReleased($event->concertBookingItem->concert)
            : false;
    }

    public function acceptsRegistration(SchedulingEvent $event): bool
    {
        return $event->cancelled_at === null && $event->event_date !== null
            && $event->event_date->betweenIncluded(today(), today()->addDays(7))
            && ! $this->eventIsReleased($event);
    }

    private function competitions(): Collection
    {
        $events = SchedulingEvent::query()->where('event_type', 'competition')->whereNull('cancelled_at')
            ->whereDate('event_date', '<=', today()->addDays(7))->with('competitionContact')
            ->orderByDesc('event_date')->limit(150)->get();
        $presentation = $this->settings->forEvents($events);

        return $events->map(function (SchedulingEvent $event) use ($presentation): ?array {
            $released = $this->competitionIsReleased($event);
            if (! $released && ! $this->acceptsRegistration($event)) {
                return null;
            }
            $display = $presentation[$event->uuid] ?? [];

            return [
                'type' => 'competition', 'title' => $display['title'] ?? $event->name,
                'subtitle' => $event->competitionContact?->name ?? 'Competition', 'date' => $event->event_date,
                'image' => $event->crewLogoUrl(), 'released' => $released, 'event' => $event,
                'concert' => null, 'is_photo_cover' => false,
            ];
        })->filter()->values();
    }

    public function studios(): Collection
    {
        return $this->concerts()->groupBy(fn (array $card) => ($card['concert']?->studio ?? $card['event']?->studio)?->id)
            ->filter(fn (Collection $cards, $id) => (bool) $id)
            ->map(function (Collection $cards): array {
                $card = $cards->first();
                $studio = $card['concert']?->studio ?? $card['event']->studio;

                return ['type' => 'studio', 'studio' => $studio, 'title' => $studio->name,
                    'image' => $studio->logoUrl(), 'released' => true, 'date' => null,
                    'subtitle' => null, 'event' => null, 'concert' => null];
            })->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function concerts(?Studio $studio = null): Collection
    {
        // Release batches control publication and email, not customer gallery folders.
        $released = Concert::query()->when($studio, fn ($query) => $query->where('studio_id', $studio->id))
            ->with('studio', 'bookingItem.schedulingEvent', 'mediaCollections')
            ->whereHas('mediaCollections', fn ($query) => $query->where('media_type', 'photo')->where('status', 'published')
                ->whereIn('visibility', ['customer', 'public'])->whereNotNull('published_at'))
            ->orderByDesc('event_date')->limit(150)->get()
            ->filter(fn (Concert $concert) => $this->concertIsReleased($concert))
            ->map(function (Concert $concert): array {
                $cover = app(CustomerConcertGallery::class)->parentCoverAsset($concert);

                return [
                    'type' => 'concert', 'title' => $concert->name, 'subtitle' => $concert->studio->name,
                    'date' => $concert->event_date, 'image' => $cover ? route('guest-galleries.concerts.image', [
                        'concert' => $concert, 'asset' => $cover->uuid, 'size' => 'cover',
                    ]) : $concert->studio->logoUrl(), 'released' => true,
                    'event' => $concert->bookingItem?->schedulingEvent, 'concert' => $concert, 'is_photo_cover' => $cover !== null,
                ];
            });
        $releasedEventIds = $released->pluck('event.id')->filter()->all();
        $upcoming = SchedulingEvent::query()->where('event_type', 'concert')->whereNull('cancelled_at')
            ->when($studio, fn ($query) => $query->where('studio_id', $studio->id))
            ->whereBetween('event_date', [today(), today()->addDays(7)])->whereNotIn('id', $releasedEventIds)
            ->with('studio', 'concertBookingItem.concert')->orderBy('event_date')->get()
            ->reject(fn (SchedulingEvent $event) => $this->eventIsReleased($event))
            ->map(fn (SchedulingEvent $event): array => [
                'type' => 'concert', 'title' => $event->name, 'subtitle' => $event->studio?->name ?? 'Concert',
                'date' => $event->event_date, 'image' => $event->crewLogoUrl(), 'released' => false,
                'event' => $event, 'concert' => $event->concertBookingItem?->concert, 'is_photo_cover' => false,
            ]);

        return $upcoming->concat($released)->sortByDesc('date')->values();
    }
}
