<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Bookings\Actions\ApproveConcertBooking;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BulkUpdateSchedulingEvents
{
    public function __construct(private readonly UpdateAvailabilityRound $updateAvailability, private readonly PublishRoster $publishRoster, private readonly ApproveConcertBooking $approveBooking) {}

    public function execute(Collection $events, Collection $bookingItems, string $action, ?string $deadline, User $staff): int
    {
        return DB::transaction(function () use ($events, $bookingItems, $action, $deadline, $staff): int {
            if ($action === 'approve') {
                $pendingItems = $bookingItems->where('approval_status', 'pending');
                foreach ($pendingItems as $item) {
                    $this->approveBooking->approveItem($item, $staff);
                }

                return $pendingItems->count();
            }
            foreach ($events as $event) {
                if ($action === 'publish_roster') {
                    $this->publishRoster->execute($event);
                } else {
                    $this->updateAvailability->execute($event, ['availability_status' => $action, 'availability_deadline' => $deadline]);
                }
            }

            return $events->count();
        });
    }
}
