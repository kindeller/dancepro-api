<?php

namespace App\Features\Bookings\Actions;

use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkUpdateConcertBookingItems
{
    public function __construct(private readonly ApproveConcertBooking $approveConcertBooking) {}

    public function execute(Collection $items, string $action, User $staff, ?string $deadlineDate): int
    {
        return DB::transaction(function () use ($items, $action, $staff, $deadlineDate): int {
            $deadline = $action === 'open' ? $this->deadline($deadlineDate) : null;
            $updated = 0;

            foreach ($items as $item) {
                if ($action === 'approve') {
                    if ($item->approval_status === 'pending') {
                        $this->approveConcertBooking->approveItem($item, $staff);
                        $updated++;
                    }

                    continue;
                }

                if (! $item->schedulingEvent) {
                    throw ValidationException::withMessages([
                        'event_ids' => "{$item->title} must be approved before availability can change.",
                    ]);
                }

                $item->schedulingEvent->update([
                    'availability_status' => $action === 'open'
                        ? AvailabilityRoundStatus::Open
                        : AvailabilityRoundStatus::Closed,
                    ...($deadline ? ['availability_deadline' => $deadline] : []),
                ]);
                $updated++;
            }

            return $updated;
        });
    }

    private function deadline(?string $date): Carbon
    {
        $deadline = Carbon::createFromFormat('Y-m-d H:i', "{$date} 17:00", config('app.timezone'));

        if ($deadline->isPast()) {
            throw ValidationException::withMessages(['deadline_date' => 'The deadline must be in the future.']);
        }

        return $deadline;
    }
}
