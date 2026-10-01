<?php

namespace App\Features\Media\Services;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;

class AdminConcertAlbums
{
    public function __construct(private readonly AdminTopPics $pics) {}

    public function browse(?string $studioUuid, bool $unlinked, bool $topPicsOnly = false): array
    {
        $studio = $studioUuid ? Studio::where('uuid', $studioUuid)->firstOrFail() : null;
        $query = SchedulingEvent::query()->where('event_type', 'concert')->whereNull('cancelled_at');
        if ($topPicsOnly) {
            $query->whereIn('scheduling_events.id', $this->pics->files()->select('galleries.scheduling_event_id'));
        }
        $showEvents = $studio !== null || $unlinked;
        $unlinkedCount = $showEvents ? 0 : (clone $query)->whereNull('studio_id')->count();
        $cards = $showEvents
            ? $query->when($studio, fn ($q) => $q->where('studio_id', $studio->id), fn ($q) => $q->whereNull('studio_id'))
                ->leftJoinSub($this->pics->counts(), 'top_pics', 'top_pics.scheduling_event_id', '=', 'scheduling_events.id')
                ->select('scheduling_events.*', 'top_pics.cover_uuid', 'top_pics.top_pic_count')
                ->with('studio', 'concertBookingItem')->orderByDesc('event_date')->orderByDesc('scheduling_events.id')
                ->paginate(24)->withQueryString()
            : Studio::whereIn('id', $query->select('studio_id'))->orderBy('name')->orderBy('id')->paginate(24)->withQueryString();

        return compact('studio', 'unlinked', 'showEvents', 'unlinkedCount', 'cards');
    }
}
