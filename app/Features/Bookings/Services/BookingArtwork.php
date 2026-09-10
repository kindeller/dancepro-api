<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Studios\Models\Studio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingArtwork
{
    /** @return Collection<string, string|null> */
    public function forItems(Collection $items): Collection
    {
        $names = $items->whereNull('scheduling_event_id')
            ->map(fn (ConcertBookingItem $item): string => mb_strtolower(trim($item->booking->studio_name)))
            ->filter()->unique()->values();
        $studios = $names->isEmpty() ? collect() : Studio::query()
            ->whereIn(DB::raw('lower(trim(name))'), $names)
            ->get(['id', 'name', 'logo_path'])
            ->groupBy(fn (Studio $studio): string => mb_strtolower(trim($studio->name)));

        return $items->mapWithKeys(function (ConcertBookingItem $item) use ($studios): array {
            if ($item->schedulingEvent) {
                return [$item->uuid => $item->schedulingEvent->crewLogoUrl()];
            }

            $matches = $studios->get(mb_strtolower(trim($item->booking->studio_name)), collect());
            $url = $matches->count() === 1 ? $matches->first()->logoUrl() : null;

            return [$item->uuid => $url !== null ? url($url) : null];
        });
    }
}
