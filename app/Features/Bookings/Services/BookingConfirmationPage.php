<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Tracking\Services\EmailBranding;
use App\Models\User;

class BookingConfirmationPage
{
    public function __construct(private readonly ConcertTracker $tracker, private readonly BookingTrackerBar $bar, private readonly EmailBranding $branding) {}

    public function data(ConcertBooking $booking, User $staff, array $context): array
    {
        abort_unless($booking->status->value === 'approved', 404);
        $event = $booking->items()->whereNull('cancelled_at')->whereNotNull('scheduling_event_id')->orderBy('event_date')->first()?->schedulingEvent;
        abort_unless($event, 404);

        return $this->tracker->detail($event, 'confirmation_information_email') + [
            'booking' => $booking, 'trackerBar' => $this->bar->page($booking, $context),
            'emailBranding' => $this->branding->for($staff->id),
        ];
    }
}
