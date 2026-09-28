<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Collection;

class BookingSeatsAndFamilies
{
    public function concerts(ConcertBooking $booking): Collection
    {
        $booking->loadMissing(['familyCountConfirmedBy', 'items.schedulingEvent.roleRequirements.crewRole', 'items.schedulingEvent.roleSeats.crewRole', 'items.schedulingEvent.roleSeats.submittedBy']);

        return $booking->items->filter(fn ($item) => ! $item->cancelled_at && ! in_array($item->approval_status, ['cancelled', 'declined'], true) && $item->item_type === ConcertBookingItemType::Concert);
    }

    public function status(SchedulingEvent $event): array
    {
        $booking = $event->concertBookingItem?->booking;
        $url = $booking ? route('admin.concert-bookings.show', [$booking, 'tab' => 'seats-families']) : route('admin.concert-bookings.index');
        $base = ['sourceUrl' => $url, 'completedAt' => null, 'activity' => null, 'seatSummary' => [], 'seatsLabel' => null, 'familiesConfirmed' => false];
        if ($event->concertBookingItem?->item_type === ConcertBookingItemType::DressRehearsal) {
            return ['key' => 'not_required', 'label' => 'Not required', 'detail' => 'Seats and family numbers are not required for DR portraits.'] + $base;
        }
        if (! $booking) {
            return ['key' => 'not_started', 'label' => 'No booking', 'detail' => 'Link the concert booking to record seats and families.'] + $base;
        }
        $concerts = $this->concerts($booking);
        $familiesConfirmed = $booking->family_count_confirmed_at !== null && $booking->final_family_count > 0;
        $complete = $familiesConfirmed && $concerts->isNotEmpty()
            && $concerts->every(fn ($item) => $item->schedulingEvent && $this->seatsComplete($item->schedulingEvent, $booking));
        $current = $concerts->firstWhere('scheduling_event_id', $event->id)?->schedulingEvent ?? $event;
        $families = $familiesConfirmed ? $booking->final_family_count : $booking->approximate_family_count;
        $result = [
            'familiesConfirmed' => $familiesConfirmed,
            'key' => $complete ? 'complete' : 'waiting',
            'label' => $families ? $families.' families'.($familiesConfirmed ? '' : ' (quoted)') : 'Families to confirm',
            'detail' => $complete ? 'Final families and seats confirmed for all concerts.' : 'Complete this task by confirming final families and reserved seats for every concert.',
        ] + $base;
        if (! $this->seatsComplete($current, $booking)) {
            $result['seatsLabel'] = 'Seats not confirmed';
        }
        $activity = collect();
        if ($familiesConfirmed) {
            $activity->push('Families: '.($booking->familyCountConfirmedBy?->name ?? 'Former user').' · '.$booking->family_count_confirmed_at->format('j M Y, g:i a'));
        }
        if ($this->seatsComplete($current, $booking)) {
            $result['seatSummary'] = $this->summary($current)->all();
            foreach ($current->roleSeats as $allocation) {
                $activity->push($this->roleLabel($allocation->crewRole->code).': '.($allocation->submittedBy?->name ?? 'Former user').' · '.$allocation->submitted_at?->format('j M Y, g:i a'));
            }
        }
        $result['activity'] = $activity->isNotEmpty() ? $activity->join(' | ') : null;
        if ($complete) {
            $result['completedAt'] = collect([$booking->family_count_confirmed_at])->merge($concerts->flatMap(fn ($item) => $item->schedulingEvent->roleSeats->pluck('submitted_at')))->filter()->max();
        }

        return $result;
    }

    public function roles(SchedulingEvent $event, ConcertBooking $booking): Collection
    {
        $event->loadMissing('roleRequirements.crewRole');
        $roles = $event->roleRequirements->pluck('crewRole')->filter(fn ($role) => str_contains($role->code, 'concert-photographer') || str_contains($role->code, 'videographer'))->keyBy('code');
        $missing = [];
        if ($booking->wants_concert_photography && ! $roles->contains(fn ($role) => str_contains($role->code, 'concert-photographer'))) {
            $missing[] = 'concert-photographer-p1';
        }
        if ($booking->wants_concert_videography && ! $roles->contains(fn ($role) => str_contains($role->code, 'videographer'))) {
            $missing[] = 'concert-videographer';
        }

        return $missing ? $roles->merge(CrewRole::whereIn('code', $missing)->get()->keyBy('code'))->values() : $roles->values();
    }

    public function seatsComplete(SchedulingEvent $event, ConcertBooking $booking): bool
    {
        $event->loadMissing(['roleSeats.crewRole']);
        $codes = $this->roles($event, $booking)->pluck('code');
        if (($booking->wants_concert_photography && ! $codes->contains(fn ($code) => str_contains($code, 'concert-photographer')))
            || ($booking->wants_concert_videography && ! $codes->contains(fn ($code) => str_contains($code, 'videographer')))) {
            return false;
        }

        return $codes->every(function ($code) use ($event): bool {
            $seats = collect($event->roleSeats->first(fn ($row) => $row->crewRole->code === $code)?->seats ?? []);
            $video = str_contains($code, 'videographer');

            return $seats->count() >= ($video ? 5 : 1) && $seats->count() <= ($video ? 6 : 2)
                && $seats->every(fn ($seat) => filled($seat['row'] ?? null) && filled($seat['seat'] ?? null));
        });
    }

    public function summary(SchedulingEvent $event): Collection
    {
        return $event->roleSeats->map(fn ($allocation) => $this->roleLabel($allocation->crewRole->code).': '.collect($allocation->seats)->map(fn ($seat) => $seat['row'].$seat['seat'])->join(', '));
    }

    private function roleLabel(string $code): string
    {
        return str_contains($code, 'videographer') ? 'V' : strtoupper(str_replace('concert-photographer-', '', $code));
    }
}
