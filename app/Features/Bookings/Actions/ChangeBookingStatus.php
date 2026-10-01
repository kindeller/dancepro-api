<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Services\BookingStatusEmails;
use App\Features\Bookings\Support\ConcertBookingStatus;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Actions\SaveConcertTrackerTaskFields;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeBookingStatus
{
    public function __construct(
        private readonly ApproveBookingForConfirmation $approveAll,
        private readonly ApproveConcertBooking $approve,
        private readonly CancelConcertBooking $cancel,
        private readonly SaveConcertTrackerTaskFields $tasks,
        private readonly BookingStatusEmails $emails,
    ) {}

    public function execute(ConcertBooking $booking, User $staff, array $input): string
    {
        return DB::transaction(function () use ($booking, $staff, $input): string {
            $booking = ConcertBooking::lockForUpdate()->findOrFail($booking->id);
            $previousStatus = $booking->status->value;
            $status = ConcertBookingStatus::from($input['status_override']);
            if ($status === ConcertBookingStatus::Approved) {
                return $this->approveAll->execute($booking, $staff, $input);
            }
            if (in_array($booking->status, [ConcertBookingStatus::Cancelled, ConcertBookingStatus::Declined], true)) {
                throw ValidationException::withMessages(['status_override' => 'This booking is closed. It cannot be reopened from the status box.']);
            }
            if (in_array($status, [ConcertBookingStatus::Pending, ConcertBookingStatus::Received], true)) {
                if ($booking->items()->whereNotNull('scheduling_event_id')->exists()) {
                    throw ValidationException::withMessages(['status_override' => 'This booking already has approved events. Cancel individual events if they will not proceed.']);
                }
                $booking->update(['status' => $status, 'reviewed_by_user_id' => $staff->id, 'reviewed_at' => now()]);
            } elseif ($status === ConcertBookingStatus::PartialApproval) {
                $ids = $input['event_ids'] ?? [];
                $pending = $booking->items()->where('approval_status', 'pending')->lockForUpdate()->get();
                $selected = $pending->whereIn('uuid', $ids);
                if ($selected->isEmpty() || $selected->count() !== count($ids)) {
                    throw ValidationException::withMessages(['event_ids' => 'Select pending events from this booking to approve.']);
                }
                if ($selected->count() === $pending->count()) {
                    throw ValidationException::withMessages(['event_ids' => 'All remaining events are selected. Choose Approved to confirm the whole booking.']);
                }
                $studio = filled($input['studio_uuid'] ?? null) ? Studio::where('uuid', $input['studio_uuid'])->firstOrFail() : null;
                foreach ($selected as $item) {
                    $this->approve->approveItem($item, $staff, $studio);
                }
                $booking->refresh();
            } elseif ($status === ConcertBookingStatus::Cancelled) {
                if (blank($input['reason'] ?? null)) {
                    throw ValidationException::withMessages(['reason' => 'Enter the reason for cancelling this booking.']);
                }
                $this->cancel->execute($booking, $staff);
            }
            $booking->revisions()->create(['user_id' => $staff->id, 'before' => ['status' => $previousStatus], 'after' => ['status' => $booking->fresh()->status->value], 'reason' => $input['reason'] ?? 'Booking status updated.']);
            $this->tasks->execute('booking', $booking->id, 'booking_received', $staff, ['status_override' => null]);
            if ($status === ConcertBookingStatus::Pending) {
                return route('admin.concert-bookings.show', $booking);
            }
            $notification = $this->emails->draft($booking->refresh(), $staff, $input['reason'] ?? null);

            return route('admin.concert-bookings.status-email', [$booking, $notification]);
        });
    }
}
