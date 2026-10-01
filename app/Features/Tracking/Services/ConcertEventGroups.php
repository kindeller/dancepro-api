<?php

namespace App\Features\Tracking\Services;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Scheduling\Models\SchedulingEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Nearby events share communications, but retain individual programmes and seats. */
class ConcertEventGroups
{
    public const WINDOW_DAYS = 20;

    public const EMAIL_TASKS = ['concert_week_email', 'post_concert_email'];

    public function items(SchedulingEvent $event): Collection
    {
        $event->loadMissing('concertBookingItem.booking.items.schedulingEvent');
        $items = $event->concertBookingItem?->booking?->items;
        if (! $items) {
            return collect();
        }

        $groups = collect();
        $group = collect();
        $end = null;
        foreach ($items->filter(fn ($item): bool => ! $item->cancelled_at && ! $item->schedulingEvent?->cancelled_at && $item->event_date !== null)
            ->sortBy(fn ($item): string => $item->event_date->format('Y-m-d').' '.($item->starts_at ?? '00:00').' '.sprintf('%012d', $item->id)) as $item) {
            if ($end === null || $item->event_date->gt($end)) {
                if ($group->isNotEmpty()) {
                    $groups->push($group);
                }
                $group = collect();
                $end = $item->event_date->copy()->addDays(self::WINDOW_DAYS)->endOfDay();
            }
            $group->push($item);
        }
        $groups->push($group);

        return $groups->first(fn (Collection $items): bool => $items->contains('scheduling_event_id', $event->id)) ?? collect();
    }

    public function anchorId(SchedulingEvent $event): int
    {
        return (int) ($this->items($event)->first(fn ($item): bool => $item->scheduling_event_id !== null)?->scheduling_event_id ?? $event->id);
    }

    public function firstEventDate(SchedulingEvent $event): ?CarbonInterface
    {
        return $this->items($event)->first()?->event_date ?? $event->event_date;
    }

    public function emailDueDate(SchedulingEvent $event): ?CarbonInterface
    {
        return $this->firstEventDate($event)?->copy()->subDays(10);
    }

    public function lastEventDate(SchedulingEvent $event): ?CarbonInterface
    {
        return $this->items($event)->last()?->event_date ?? $event->event_date;
    }

    public function postConcertDueDate(SchedulingEvent $event): ?CarbonInterface
    {
        return $this->lastEventDate($event)?->copy()->addDay()->startOfDay();
    }

    public function firstConcertDate(SchedulingEvent $event): ?CarbonInterface
    {
        if (! $event->concertBookingItem) {
            $event->loadMissing('concertBookingItem');
        }
        if (! $event->concertBookingItem) {
            return $event->event_date;
        }

        return $this->items($event)->first(fn (ConcertBookingItem $item): bool => $item->item_type === ConcertBookingItemType::Concert)?->event_date;
    }

    public function dueDate(SchedulingEvent $event, int $daysBefore): ?CarbonInterface
    {
        return $this->firstConcertDate($event)?->copy()->subDays($daysBefore);
    }
}
