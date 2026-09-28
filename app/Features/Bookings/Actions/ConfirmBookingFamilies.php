<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Services\BookingChangeSnapshot;
use App\Features\Tracking\Services\TrackerEmailContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmBookingFamilies
{
    public function __construct(private readonly BookingChangeSnapshot $snapshots, private readonly TrackerEmailContext $emails) {}

    public function execute(ConcertBooking $booking, int $count, User $actor): void
    {
        DB::transaction(function () use ($booking, $count, $actor): void {
            $booking = ConcertBooking::lockForUpdate()->findOrFail($booking->id);
            if ($booking->cancelled_at || ! $booking->items()->where('item_type', 'concert')->whereNull('cancelled_at')->exists()) {
                throw ValidationException::withMessages(['final_family_count' => 'Final family numbers are only required for an active concert booking.']);
            }
            if ($booking->final_family_count === $count && $booking->family_count_confirmed_at) {
                return;
            }
            $before = $this->snapshots->forBooking($booking);
            $booking->update(['final_family_count' => $count, 'family_count_confirmed_at' => now(), 'family_count_confirmed_by' => $actor->id]);
            $booking->revisions()->create(['user_id' => $actor->id, 'before' => $before, 'after' => $this->snapshots->forBooking($booking), 'reason' => 'Final performing family numbers confirmed.']);
            $this->emails->invalidate($booking, 'Final family numbers changed. Review booking communications before rescheduling.');
        });
    }
}
