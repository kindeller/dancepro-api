<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Scheduling\Actions\ResetAssignmentAcknowledgements;
use App\Features\Venues\Models\Venue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateConcertBooking
{
    public function __construct(private readonly ResetAssignmentAcknowledgements $resetAcknowledgements) {}

    public function execute(ConcertBooking $booking, array $data): ConcertBooking
    {
        return DB::transaction(function () use ($booking, $data): ConcertBooking {
            $items = collect($data['items']);
            $known = $booking->items()->pluck('uuid');
            if ($items->pluck('uuid')->diff($known)->isNotEmpty() || $known->diff($items->pluck('uuid'))->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Every existing booking event must be included.']);
            }
            $booking->update(collect($data)->except('items')->all());
            foreach ($items as $values) {
                $this->updateItem($booking, $booking->items()->where('uuid', $values['uuid'])->firstOrFail(), $values);
            }

            return $booking->refresh()->load('items');
        });
    }

    private function updateItem(ConcertBooking $booking, ConcertBookingItem $item, array $data): void
    {
        if ($item->approval_status === 'cancelled') {
            return;
        }

        $venueUuid = $data['venue_uuid'];
        unset($data['uuid'], $data['venue_uuid']);
        if ($venueUuid === 'other') {
            $data['venue_id'] = null;
        } else {
            $venue = Venue::query()->where('uuid', $venueUuid)->firstOrFail();
            $data['venue_id'] = $venue->id;
            $data['venue_name'] = $venue->name;
            $data['venue_address'] = collect([$venue->address_line_1, $venue->address_line_2, trim(collect([$venue->suburb, $venue->state, $venue->postcode])->filter()->join(' '))])->filter()->join(', ');
        }
        $item->update($data);
        $event = $item->schedulingEvent;
        if (! $event) {
            return;
        }
        $event->update(['name' => $booking->studio_name, 'venue_id' => $item->venue_id, 'event_type_definition_id' => $item->event_type_definition_id, 'event_date' => $item->event_date]);
        $materialChanged = $event->wasChanged(['name', 'venue_id', 'event_type_definition_id', 'event_date']);
        $starts = Carbon::parse($item->event_date->toDateString().' '.$item->starts_at);
        $finishes = Carbon::parse($item->event_date->toDateString().' '.$item->finishes_at);
        $isVideo = $item->item_type === ConcertBookingItemType::Concert && $booking->wants_concert_videography;
        foreach ($event->shifts as $shift) {
            $shift->update([
                'shift_date' => $item->event_date,
                'posted_arrival_at' => $starts->copy()->subMinutes($isVideo ? 90 : 60),
                'starts_at' => $starts,
                'estimated_finish_at' => $finishes->copy()->addMinutes(20),
            ]);
            $materialChanged = $materialChanged || $shift->wasChanged(['shift_date', 'posted_arrival_at', 'starts_at', 'estimated_finish_at']);
        }

        if ($materialChanged) {
            $this->resetAcknowledgements->execute($event, 'The event name, date, venue, event type or shift times changed');
        }
    }
}
