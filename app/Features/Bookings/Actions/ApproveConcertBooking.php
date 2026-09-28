<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Services\BookingCrewRequirements;
use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Bookings\Support\ConcertBookingStatus;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveConcertBooking
{
    public function __construct(private readonly PrepareBookingConcert $media, private readonly BookingCrewRequirements $crew) {}

    public function execute(ConcertBooking $booking, User $reviewedBy, ?string $note, ?Studio $studio = null): ConcertBooking
    {
        return DB::transaction(function () use ($booking, $reviewedBy, $note, $studio): ConcertBooking {
            foreach ($booking->items->where('approval_status', 'pending') as $item) {
                $this->approveItem($item, $reviewedBy, $studio);
            }

            $booking->update(['internal_review_note' => $note]);

            return $booking->refresh();
        });
    }

    public function approveItem(ConcertBookingItem $item, User $reviewedBy, ?Studio $studio = null): SchedulingEvent
    {
        if ($item->approval_status !== 'pending') {
            throw ValidationException::withMessages(['events' => 'Only pending events can be approved.']);
        }

        return DB::transaction(function () use ($item, $reviewedBy, $studio): SchedulingEvent {
            $booking = ConcertBooking::query()->lockForUpdate()->findOrFail($item->concert_booking_id);
            $item = ConcertBookingItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($item->approval_status !== 'pending') {
                throw ValidationException::withMessages(['events' => 'This event has already been reviewed. Reload the booking.']);
            }
            if ($studio && $booking->studio_id && $studio->id !== $booking->studio_id) {
                throw ValidationException::withMessages(['studio_uuid' => 'This booking is linked to another studio. Resolve the studio association before approving it.']);
            }
            $studio ??= $booking->studio;
            if ($studio && ! $booking->studio_id) {
                $booking->update(['studio_id' => $studio->id]);
            }
            $venue = $item->venue;
            if (! $venue) {
                $itemLabel = $item->title ?: str_replace('_', ' ', $item->item_type->value);
                throw ValidationException::withMessages([
                    'venue' => "Resolve the venue for {$itemLabel} before approving it.",
                ]);
            }
            $event = SchedulingEvent::query()->create([
                'venue_id' => $venue->id,
                'studio_id' => $studio?->id,
                'event_type_definition_id' => $item->event_type_definition_id,
                'name' => $booking->studio_name,
                'event_type' => SchedulingEventType::Concert,
                'event_date' => $item->event_date,
                'availability_status' => AvailabilityRoundStatus::Draft,
                'admin_notes' => "Created from concert booking {$booking->uuid}.",
                'programme_path' => $item->programme_path,
            ]);
            $startsAt = Carbon::parse($item->event_date->toDateString().' '.$item->starts_at);
            $finishesAt = Carbon::parse($item->event_date->toDateString().' '.$item->finishes_at);
            if ($finishesAt->lte($startsAt)) {
                $finishesAt->addDay();
            }
            $isVideo = $item->item_type === ConcertBookingItemType::Concert && $booking->wants_concert_videography;
            $event->shifts()->create([
                'period' => null,
                'shift_date' => $item->event_date,
                'requires_setup' => true,
                'requires_set_down' => true,
                'posted_arrival_at' => $startsAt->copy()->subMinutes($isVideo ? 90 : 60),
                'starts_at' => $startsAt,
                'estimated_finish_at' => $finishesAt->copy()->addMinutes(20),
            ]);
            foreach ($this->crew->roles($booking, $item->item_type) as $role) {
                $event->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
            }
            $item->update([
                'approval_status' => 'approved',
                'approved_by_user_id' => $reviewedBy->id,
                'approved_at' => now(),
                'scheduling_event_id' => $event->id,
            ]);

            $this->media->execute($item->fresh(['schedulingEvent', 'booking', 'concert']), $reviewedBy);

            if (! $booking->items()->where('approval_status', 'pending')->exists()) {
                $booking->update([
                    'status' => ConcertBookingStatus::Approved,
                    'reviewed_by_user_id' => $reviewedBy->id,
                    'reviewed_at' => now(),
                ]);
            }

            return $event;
        });
    }
}
