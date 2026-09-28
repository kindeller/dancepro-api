<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Tracking\Services\EmailBranding;
use App\Models\User;

class BookingStudioMediaWorkspace
{
    public function __construct(private readonly ConcertTracker $tracker, private readonly BookingTrackerBar $bar, private readonly EmailBranding $branding) {}

    public function page(ConcertBooking $booking, ?string $eventUuid, User $actor, array $context = []): array
    {
        $events = $booking->items->where('approval_status', '!=', 'cancelled')->pluck('schedulingEvent')->filter()->values();
        $event = $eventUuid ? $events->firstWhere('uuid', $eventUuid) : $events->first();
        abort_if($eventUuid && ! $event, 404);
        if (! $event) {
            return ['studioMediaEvents' => $events, 'studioMediaWorkspace' => null, 'trackerBar' => $this->bar->pendingPage($booking, 'slideshow_received', $context)];
        }
        $detail = $this->tracker->detail($event, 'slideshow_received');

        return [
            'studioMediaEvents' => $events,
            'studioMediaWorkspace' => $detail + ['item' => $event->concertBookingItem, 'emailBranding' => $this->branding->for($actor->id)],
            'trackerBar' => $this->bar->studioMediaPage($detail, $context),
        ];
    }
}
