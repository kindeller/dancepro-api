<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Scheduling\Actions\ResetAssignmentAcknowledgements;
use Carbon\Carbon;

class SyncBookingEvent
{
    public function __construct(
        private readonly BookingCrewRequirements $crew,
        private readonly ResetAssignmentAcknowledgements $acknowledgements,
    ) {}

    public function execute(ConcertBooking $booking, ConcertBookingItem $item, array $previous, bool $previousVideo, ?string $accessTime): void
    {
        $event = $item->schedulingEvent;
        if (! $event) {
            return;
        }
        $event->update(['name' => $booking->studio_name, 'venue_id' => $item->venue_id, 'event_type_definition_id' => $item->event_type_definition_id, 'event_date' => $item->event_date]);
        $changed = $event->wasChanged(['name', 'venue_id', 'event_type_definition_id', 'event_date']);
        $oldStart = Carbon::parse($previous['date'].' '.$previous['start']);
        $oldFinish = Carbon::parse($previous['date'].' '.$previous['finish']);
        if ($oldFinish->lte($oldStart)) {
            $oldFinish->addDay();
        }
        $start = Carbon::parse($item->event_date->toDateString().' '.$item->starts_at);
        $finish = Carbon::parse($item->event_date->toDateString().' '.$item->finishes_at);
        if ($finish->lte($start)) {
            $finish->addDay();
        }
        $video = $item->item_type === ConcertBookingItemType::Concert && $booking->wants_concert_videography;
        $startDelta = $oldStart->diffInSeconds($start, false);
        $finishDelta = $oldFinish->diffInSeconds($finish, false);
        $shifts = $event->shifts->sortBy('posted_arrival_at');
        foreach ($shifts as $shift) {
            $arrival = $shift->posted_arrival_at?->copy()->addSeconds($startDelta);
            // Follow the default only if staff have not deliberately adjusted it.
            if ($shift->posted_arrival_at?->equalTo($oldStart->copy()->subMinutes($previousVideo ? 90 : 60))) {
                $arrival = $start->copy()->subMinutes($video ? 90 : 60);
            }
            if ($accessTime && $shift->id === $shifts->first()->id) {
                $arrival = Carbon::parse($item->event_date->toDateString().' '.$accessTime);
                if ($arrival->gt($start)) {
                    $arrival->subDay();
                }
            }
            $shift->update([
                'shift_date' => ($shift->starts_at?->copy()->addSeconds($startDelta) ?? $start)->toDateString(),
                'posted_arrival_at' => $arrival,
                'starts_at' => $shift->starts_at?->copy()->addSeconds($startDelta) ?? $start,
                'estimated_finish_at' => $shift->estimated_finish_at?->copy()->addSeconds($finishDelta) ?? $finish->copy()->addMinutes(20),
            ]);
            $changed = $shift->wasChanged(['shift_date', 'posted_arrival_at', 'starts_at', 'estimated_finish_at']) || $changed;
        }
        $changed = $this->crew->sync($event, $booking, $item->item_type) || $changed;
        $item->concert?->update([
            'name' => $item->title ?: ($item->item_type === ConcertBookingItemType::DressRehearsal ? 'DR Portraits' : 'Concert').' '.$item->event_date->format('j M Y'),
            'event_date' => $item->event_date,
            'venue_name' => $item->venue_name,
        ]);
        if ($video && $item->concert && ! $item->concert->requires_approval) {
            $item->concert->update(['requires_approval' => true]);
        }
        if ($changed) {
            $this->acknowledgements->execute($event, 'Booking details or services changed. Review crew assignments and timings.');
        }
    }
}
