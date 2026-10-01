<?php

namespace App\Features\Website\Services;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Support\SchedulingEventType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WebsiteEvents
{
    public function upcoming(): Collection
    {
        $today = today();
        $until = $today->copy()->addMonthsNoOverflow(6);

        return SchedulingEvent::where('website_hidden', false)->whereNull('cancelled_at')
            ->where(fn ($q) => $q->whereDate('event_date', '<=', $until)
                ->orWhereHas('shifts', fn ($q) => $q->whereDate('shift_date', '<=', $until)))
            ->where(fn ($q) => $q->whereDate('event_date', '>=', $today)
                ->orWhereHas('shifts', fn ($q) => $q->whereDate('shift_date', '>=', $today)))
            ->with(['venue', 'studio', 'competitionContact'])
            ->withMin('shifts', 'shift_date')->withMax('shifts', 'shift_date')->get()
            ->map(function (SchedulingEvent $event) {
                $dates = collect([$event->event_date?->toDateString(), $event->shifts_min_shift_date, $event->shifts_max_shift_date])->filter()->sort()->values();

                return ['event' => $event, 'start' => Carbon::parse($dates->first()), 'end' => Carbon::parse($dates->last())];
            })
            ->filter(fn ($entry) => $entry['start']->lte($until) && $entry['end']->gte($today))
            ->sortBy(fn ($entry) => $entry['start']->toDateString())->values();
    }

    public function homepage(): array
    {
        $events = $this->upcoming();

        return [
            'competitions' => $events->filter(fn ($entry) => $entry['event']->event_type === SchedulingEventType::Competition)->values(),
            'concertStudios' => $events->filter(fn ($entry) => $entry['event']->event_type === SchedulingEventType::Concert)
                ->map(fn ($entry) => $entry['event']->studio)->filter()->unique('id')->values(),
        ];
    }
}
