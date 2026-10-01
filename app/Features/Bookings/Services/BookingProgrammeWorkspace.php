<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Programmes\Services\ProgrammePreparation;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertTracker;
use Illuminate\Support\Facades\Gate;

class BookingProgrammeWorkspace
{
    public function __construct(private readonly ProgrammePreparation $preparation, private readonly ConcertTracker $tracker, private readonly BookingTrackerBar $bar) {}

    public function page(ConcertBooking $booking, ?string $eventUuid, array $context = []): array
    {
        $events = $booking->items->where('approval_status', '!=', 'cancelled')->pluck('schedulingEvent')->filter()->values();
        $event = $eventUuid ? $events->firstWhere('uuid', $eventUuid) : ($events->first(fn ($candidate): bool => $candidate->concertBookingItem?->item_type !== ConcertBookingItemType::DressRehearsal) ?? $events->first());
        abort_if($eventUuid && ! $event, 404);
        if (! $event) {
            return ['programmeEvents' => $events, 'programmeWorkspace' => null, 'trackerBar' => $this->bar->pendingPage($booking, 'programme_prepared', $context)];
        }
        Gate::authorize('viewProgramme', $event);

        $notRequired = $event->concertBookingItem?->item_type === ConcertBookingItemType::DressRehearsal;

        return ['programmeEvents' => $events, 'selectedProgrammeEvent' => $event, 'programmeNotRequired' => $notRequired, 'programmeWorkspace' => $notRequired ? null : $this->preparation->editor($event),
            'trackerBar' => $this->bar->sourcePage($this->tracker->detail($event, 'programme_prepared'), $context)];
    }
}
