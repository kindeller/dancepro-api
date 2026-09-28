<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Services\BookingChangeSnapshot;
use App\Features\Bookings\Services\SyncBookingEvent;
use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Studios\Models\StudioBookingChangeRequest;
use App\Features\Tracking\Models\ConcertTrackerAlert;
use App\Features\Tracking\Services\TrackerEmailContext;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateConcertBooking
{
    public function __construct(
        private readonly BookingChangeSnapshot $snapshots,
        private readonly SyncBookingEvent $sync,
        private readonly TrackerEmailContext $emails,
    ) {}

    public function execute(ConcertBooking $booking, array $data, ?User $actor = null): ConcertBooking
    {
        return DB::transaction(function () use ($booking, $data, $actor): ConcertBooking {
            $booking = ConcertBooking::lockForUpdate()->findOrFail($booking->id);
            if ($booking->cancelled_at) {
                throw ValidationException::withMessages(['booking' => 'A cancelled booking cannot be edited.']);
            }
            $items = collect($data['items']);
            $known = $booking->items()->pluck('uuid');
            if ($items->pluck('uuid')->diff($known)->isNotEmpty() || $known->diff($items->pluck('uuid'))->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Every existing booking event must be included.']);
            }
            $change = $this->requestToApply($booking, $data, $actor);
            $before = $this->snapshots->forBooking($booking);
            $previousVideo = $booking->wants_concert_videography;
            $booking->update(collect($data)->except(['items', 'booking_change_uuid', 'resolution_note'])->all());
            $concertLineupChanged = $previousVideo !== $booking->wants_concert_videography
                || $items->contains(fn ($values) => $booking->items->firstWhere('uuid', $values['uuid'])->item_type->value !== $values['item_type']);
            if ($concertLineupChanged && $booking->final_family_count !== null) {
                $booking->update(['final_family_count' => null, 'family_count_confirmed_at' => null, 'family_count_confirmed_by' => null]);
            }
            foreach ($items as $values) {
                $this->updateItem($booking, $booking->items()->where('uuid', $values['uuid'])->firstOrFail(), $values, $previousVideo);
            }
            $after = $this->snapshots->forBooking($booking->refresh());
            $changed = json_encode($before, JSON_THROW_ON_ERROR) !== json_encode($after, JSON_THROW_ON_ERROR);
            if ($change && ! $changed) {
                throw ValidationException::withMessages(['booking_change_uuid' => 'Apply the agreed changes before completing this request, or decline it from the event page.']);
            }
            if ($changed) {
                $booking->revisions()->create([
                    'user_id' => $actor?->id, 'studio_booking_change_request_id' => $change?->id,
                    'before' => $before, 'after' => $after, 'reason' => $data['resolution_note'] ?? null,
                ]);
                $this->emails->invalidate($booking, 'Booking details changed. Review and schedule a replacement using the current details.');
            }
            if ($change) {
                $change->update(['status' => 'completed', 'resolved_by_user_id' => $actor->id, 'resolved_at' => now(), 'resolution_note' => $data['resolution_note']]);
                ConcertTrackerAlert::where('dedupe_key', 'like', 'studio-booking-change:'.$change->id.':%')->whereNull('resolved_at')->update(['resolved_at' => now()]);
            }

            return $booking->load('items');
        });
    }

    private function requestToApply(ConcertBooking $booking, array $data, ?User $actor): ?StudioBookingChangeRequest
    {
        if (empty($data['booking_change_uuid'])) {
            return null;
        }
        $change = StudioBookingChangeRequest::where('uuid', $data['booking_change_uuid'])
            ->whereIn('scheduling_event_id', $booking->items()->whereNull('cancelled_at')->pluck('scheduling_event_id')->filter())->lockForUpdate()->first();
        if (! $actor || ! $change || ! in_array($change->status, ['requested', 'in_review'], true) || blank($data['resolution_note'] ?? null)) {
            throw ValidationException::withMessages(['booking_change_uuid' => 'Choose an open request for this booking and record the agreed resolution.']);
        }

        return $change;
    }

    private function updateItem(ConcertBooking $booking, ConcertBookingItem $item, array $data, bool $previousVideo): void
    {
        if ($item->approval_status === 'cancelled') {
            return;
        }
        $previous = ['date' => $item->event_date->toDateString(), 'start' => $item->starts_at, 'finish' => $item->finishes_at];
        $wasVideo = $item->item_type === ConcertBookingItemType::Concert && $previousVideo;
        $venueUuid = $data['venue_uuid'];
        $accessTime = $data['access_time'] ?? null;
        unset($data['uuid'], $data['venue_uuid'], $data['access_time']);
        if ($venueUuid === 'other') {
            $data['venue_id'] = null;
        } else {
            $venue = Venue::where('uuid', $venueUuid)->firstOrFail();
            $data['venue_id'] = $venue->id;
            $data['venue_name'] = $venue->name;
            $data['venue_address'] = collect([$venue->address_line_1, $venue->address_line_2, trim(collect([$venue->suburb, $venue->state, $venue->postcode])->filter()->join(' '))])->filter()->join(', ');
        }
        $item->update($data);
        $this->sync->execute($booking, $item, $previous, $wasVideo, $accessTime);
    }
}
