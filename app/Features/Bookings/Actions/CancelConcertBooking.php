<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Support\ConcertBookingStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelConcertBooking
{
    public function __construct(private readonly CancelConcertBookingItem $cancelItem) {}

    public function execute(ConcertBooking $booking, User $user): ConcertBooking
    {
        return DB::transaction(function () use ($booking, $user): ConcertBooking {
            $booking->items()->where('approval_status', '!=', 'cancelled')->get()->each(
                fn ($item) => $this->cancelItem->execute($item, $user),
            );
            $booking->update(['status' => ConcertBookingStatus::Cancelled, 'cancelled_by_user_id' => $user->id, 'cancelled_at' => now()]);

            return $booking->refresh();
        });
    }
}
