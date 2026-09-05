<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Support\ConcertBookingStatus;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelConcertBookingItem
{
    public function execute(ConcertBookingItem $item, User $user): ConcertBookingItem
    {
        if ($item->approval_status === 'cancelled') {
            throw ValidationException::withMessages(['event' => 'This event has already been cancelled.']);
        }

        return DB::transaction(function () use ($item, $user): ConcertBookingItem {
            $item->update([
                'approval_status' => 'cancelled',
                'cancelled_by_user_id' => $user->id,
                'cancelled_at' => now(),
            ]);

            $event = $item->schedulingEvent;
            $event?->update([
                'availability_status' => AvailabilityRoundStatus::Closed,
                'availability_deadline' => null,
                'cancelled_by_user_id' => $user->id,
                'cancelled_at' => now(),
            ]);
            $assignments = $event?->shifts()->with('assignments.crewProfile')->get()
                ->flatMap->assignments ?? collect();

            $assignments
                ->filter(fn ($assignment): bool => $assignment->status === 'published' && $assignment->crewProfile?->user_id !== null)
                ->each(function ($assignment) use ($event): void {
                    CrewNotification::query()
                        ->where('user_id', $assignment->crewProfile->user_id)
                        ->where('destination_type', 'assignment')
                        ->where('destination_id', $assignment->uuid)
                        ->whereIn('type', ['shift_allocation', 'shift_changed'])
                        ->whereNull('read_at')
                        ->update(['read_at' => now()]);
                    CrewNotification::query()->create([
                        'user_id' => $assignment->crewProfile->user_id,
                        'type' => 'event_cancelled',
                        'title' => 'Shift cancelled',
                        'message' => sprintf(
                            '%s on %s has been cancelled. No availability, cover, clocking or timesheet action is required.',
                            $event->name,
                            ($assignment->shift->shift_date ?? $event->event_date)->format('D j M Y'),
                        ),
                        'destination_type' => 'assignment',
                        'destination_id' => $assignment->uuid,
                    ]);
                });

            $event?->shifts()->with('assignments.coverRequests.recipients')->get()
                ->flatMap->assignments
                ->flatMap->coverRequests
                ->where('status', 'open')
                ->each(function ($coverRequest): void {
                    $coverRequest->update(['status' => 'closed']);
                    $coverRequest->recipients()->where('status', 'pending')->update(['status' => 'closed', 'responded_at' => now()]);
                });

            $booking = $item->booking;
            if (! $booking->items()->where('approval_status', '!=', 'cancelled')->exists()) {
                $booking->update([
                    'status' => ConcertBookingStatus::Cancelled,
                    'cancelled_by_user_id' => $user->id,
                    'cancelled_at' => now(),
                ]);
            } elseif (! $booking->items()->where('approval_status', 'pending')->exists()) {
                $booking->update([
                    'status' => ConcertBookingStatus::Approved,
                    'reviewed_by_user_id' => $user->id,
                    'reviewed_at' => now(),
                ]);
            }

            return $item->refresh();
        });
    }
}
