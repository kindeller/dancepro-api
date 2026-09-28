<?php

namespace Database\Seeders;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Services\BookingChangeSnapshot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RepairLocalTrackerBookingLinksSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Local tracker booking repair may only run locally.');
        }

        $count = DB::transaction(function (): int {
            $count = 0;
            $snapshots = app(BookingChangeSnapshot::class);
            $bookings = ConcertBooking::whereNull('studio_id')->where('status', 'approved')
                ->where('contact_email', 'like', '%.test')->lockForUpdate()->get();
            foreach ($bookings as $booking) {
                $events = $booking->items->whereNull('cancelled_at')->map->schedulingEvent;
                $studios = $events->filter()->pluck('studio_id')->filter()->unique();
                if ($events->isEmpty() || $events->contains(fn ($event) => ! $event?->studio_id) || $studios->count() !== 1) {
                    continue;
                }
                $before = $snapshots->forBooking($booking);
                $booking->update(['studio_id' => $studios->first()]);
                $booking->revisions()->create(['before' => $before, 'after' => $snapshots->forBooking($booking->fresh()),
                    'reason' => 'Local test booking studio link restored from its existing approved events.']);
                $count++;
            }

            return $count;
        });

        $this->command?->info("Restored studio links for {$count} approved local test bookings.");
    }
}
