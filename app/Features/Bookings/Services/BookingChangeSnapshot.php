<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;

class BookingChangeSnapshot
{
    public function forBooking(ConcertBooking $booking): array
    {
        $booking->load('items.schedulingEvent.shifts');

        return $booking->only(['studio_name', 'contact_name', 'contact_email', 'contact_phone', 'wants_portrait_photography', 'wants_concert_photography', 'wants_concert_videography', 'approximate_family_count', 'final_family_count', 'family_count_confirmed_at', 'concert_inclusions', 'multiple_concert_relationship', 'accepted_requirements', 'postal_address', 'previous_video_feedback', 'cancelled_at']) + [
            'items' => $booking->items->sortBy('id')->map(fn ($item) => $item->only(['uuid', 'item_type', 'title', 'venue_id', 'venue_name', 'venue_address', 'event_type_definition_id', 'event_date', 'starts_at', 'finishes_at', 'cancelled_at']) + [
                'shifts' => $item->schedulingEvent?->shifts->sortBy('id')->map->only(['id', 'shift_date', 'posted_arrival_at', 'starts_at', 'estimated_finish_at'])->values()->all() ?? [],
            ])->values()->all(),
        ];
    }
}
