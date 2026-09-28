<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Programmes\Services\ConcertTitleCards;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertTracker;
use Illuminate\Support\Facades\Gate;

class BookingVideoTitlesWorkspace
{
    public function __construct(private readonly ConcertTitleCards $titles, private readonly ConcertTracker $tracker, private readonly BookingTrackerBar $bar) {}

    public function page(ConcertBooking $booking, ?string $eventUuid, array $context = []): array
    {
        $events = $booking->items->filter(fn ($item) => $item->item_type->value === 'concert')->where('approval_status', '!=', 'cancelled')->pluck('schedulingEvent')->filter()->values();
        $event = $eventUuid ? $events->firstWhere('uuid', $eventUuid) : $events->first();
        abort_if($eventUuid && ! $event, 404);
        if (! $event) {
            return ['titleEvents' => $events, 'titleWorkspace' => null, 'trackerBar' => $this->bar->pendingPage($booking, 'video_titles', $context)];
        }
        Gate::authorize('viewProgramme', $event);

        return ['titleEvents' => $events, 'titleWorkspace' => $this->titles->page($event),
            'trackerBar' => $this->bar->videoTitlesPage($this->tracker->detail($event, 'video_titles'), $context)];
    }
}
