<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Actions\SaveConcertTrackerTaskFields;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveBookingForConfirmation
{
    public function __construct(private readonly ApproveConcertBooking $approve, private readonly SaveConcertTrackerTaskFields $tasks) {}

    public function execute(ConcertBooking $booking, User $staff, array $input = []): string
    {
        return DB::transaction(function () use ($booking, $staff, $input): string {
            $booking = ConcertBooking::query()->lockForUpdate()->findOrFail($booking->id);
            if (! in_array($booking->status->value, ['pending', 'received', 'partial_approval', 'approved'], true)) {
                throw ValidationException::withMessages(['status_override' => 'Only an active booking can be approved.']);
            }
            $studio = filled($input['studio_uuid'] ?? null) ? Studio::where('uuid', $input['studio_uuid'])->firstOrFail() : null;
            $this->approve->execute($booking, $staff, $input['internal_review_note'] ?? $booking->internal_review_note, $studio);
            $event = $booking->items()->whereNull('cancelled_at')->whereNotNull('scheduling_event_id')->orderBy('event_date')->first()?->schedulingEvent;
            if (! $event) {
                throw ValidationException::withMessages(['events' => 'This booking has no active events to approve.']);
            }
            $this->tasks->execute('booking', $booking->id, 'booking_received', $staff, ['status_override' => null]);

            return route('admin.concert-bookings.confirmation', $booking);
        });
    }
}
