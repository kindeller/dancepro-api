<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Bookings\Support\ConcertBookingStatus;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveConcertBooking
{
    public function execute(ConcertBooking $booking, User $reviewedBy, ?string $note): ConcertBooking
    {
        return DB::transaction(function () use ($booking, $reviewedBy, $note): ConcertBooking {
            foreach ($booking->items->where('approval_status', 'pending') as $item) {
                $this->approveItem($item, $reviewedBy);
            }

            $booking->update(['internal_review_note' => $note]);

            return $booking->refresh();
        });
    }

    public function approveItem(ConcertBookingItem $item, User $reviewedBy): SchedulingEvent
    {
        if ($item->approval_status !== 'pending') {
            throw ValidationException::withMessages(['events' => 'Only pending events can be approved.']);
        }

        return DB::transaction(function () use ($item, $reviewedBy): SchedulingEvent {
            $booking = $item->booking;
            $venue = $item->venue;
            if (! $venue) {
                $itemLabel = $item->title ?: str_replace('_', ' ', $item->item_type->value);
                throw ValidationException::withMessages([
                    'venue' => "Resolve the venue for {$itemLabel} before approving it.",
                ]);
            }
            $event = SchedulingEvent::query()->create([
                'venue_id' => $venue->id,
                'event_type_definition_id' => $item->event_type_definition_id,
                'name' => $booking->studio_name,
                'event_type' => SchedulingEventType::Concert,
                'event_date' => $item->event_date,
                'availability_status' => AvailabilityRoundStatus::Draft,
                'admin_notes' => "Created from concert booking {$booking->uuid}.",
            ]);
            $startsAt = Carbon::parse($item->event_date->toDateString().' '.$item->starts_at);
            $finishesAt = Carbon::parse($item->event_date->toDateString().' '.$item->finishes_at);
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
            foreach ($this->requiredRoles($booking, $item->item_type) as $role) {
                $event->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
            }
            $item->update([
                'approval_status' => 'approved',
                'approved_by_user_id' => $reviewedBy->id,
                'approved_at' => now(),
                'scheduling_event_id' => $event->id,
            ]);

            if (! $booking->items()->where('approval_status', '!=', 'approved')->exists()) {
                $booking->update([
                    'status' => ConcertBookingStatus::Approved,
                    'reviewed_by_user_id' => $reviewedBy->id,
                    'reviewed_at' => now(),
                ]);
            }

            return $event;
        });
    }

    private function requiredRoles(ConcertBooking $booking, ConcertBookingItemType $itemType): array
    {
        $roles = [];
        if ($itemType === ConcertBookingItemType::DressRehearsal && $booking->wants_portrait_photography) {
            $roles[] = CrewRole::query()->firstOrCreate(['code' => 'photographer-p'], ['name' => 'Dress Rehearsal Photographer P', 'event_type' => 'concert', 'is_active' => true]);
            $roles[] = CrewRole::query()->firstOrCreate(['code' => 'concert-dr-portrait-assistant'], ['name' => 'Concert DR Portrait Assistant A', 'event_type' => 'concert', 'is_active' => true]);
        }
        if ($itemType === ConcertBookingItemType::Concert && $booking->wants_concert_photography) {
            $roles[] = CrewRole::query()->firstOrCreate(['code' => 'concert-photographer-p1'], ['name' => 'Concert Photographer P1', 'event_type' => 'concert', 'is_active' => true]);
        }
        if ($itemType === ConcertBookingItemType::Concert && $booking->wants_concert_videography) {
            $roles[] = CrewRole::query()->firstOrCreate(['code' => 'concert-videographer'], ['name' => 'Concert Videographer V', 'event_type' => 'concert', 'is_active' => true]);
        }

        return $roles;
    }
}
