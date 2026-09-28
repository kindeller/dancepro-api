<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Concerts\Actions\SaveConcert;
use App\Features\Concerts\Models\Concert;
use App\Models\User;

class PrepareBookingConcert
{
    public function __construct(private readonly SaveConcert $save) {}

    public function execute(ConcertBookingItem $item, User $actor): ?Concert
    {
        $studioId = $item->schedulingEvent?->studio_id;
        if (! $studioId || $item->concert) {
            return $item->concert;
        }

        return $this->save->execute([
            'studio_id' => $studioId,
            'concert_booking_item_id' => $item->id,
            'name' => $item->title ?: ($item->item_type === ConcertBookingItemType::DressRehearsal ? 'DR Portraits' : 'Concert').' '.$item->event_date->format('j M Y'),
            'event_date' => $item->event_date,
            'venue_name' => $item->venue_name,
            'status' => 'draft',
            'is_enabled' => false,
            'requires_approval' => $item->item_type === ConcertBookingItemType::Concert && $item->booking->wants_concert_videography,
            'notes' => 'Draft media record prepared from the approved booking.',
        ], $actor);
    }
}
