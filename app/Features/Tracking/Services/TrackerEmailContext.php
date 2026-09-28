<?php

namespace App\Features\Tracking\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Services\BookingChangeSnapshot;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;

class TrackerEmailContext
{
    public function hash(SchedulingEvent $event): string
    {
        $event->load('concertBookingItem.booking', 'shifts');
        $booking = $event->concertBookingItem?->booking;
        $data = $booking ? app(BookingChangeSnapshot::class)->forBooking($booking) : $event->only(['name', 'event_date', 'venue_id', 'organiser_email', 'cancelled_at']);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function invalidate(ConcertBooking $booking, string $reason): int
    {
        return ConcertTrackerEmailDelivery::whereIn('scheduling_event_id', $booking->items()->pluck('scheduling_event_id')->filter())
            ->whereIn('status', ['scheduled', 'queued', 'failed'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'invalidated_at' => now(), 'cancellation_reason' => $reason]);
    }
}
