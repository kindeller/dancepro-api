<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Collection;

class BookingCrewRequirements
{
    private const ROLES = [
        'photographer-p' => 'Dress Rehearsal Photographer P',
        'concert-dr-portrait-assistant' => 'Concert DR Portrait Assistant A',
        'concert-photographer-p1' => 'Concert Photographer P1',
        'concert-videographer' => 'Concert Videographer V',
    ];

    public function roles(ConcertBooking $booking, ConcertBookingItemType $type): Collection
    {
        $codes = $type === ConcertBookingItemType::DressRehearsal
            ? ($booking->wants_portrait_photography ? ['photographer-p', 'concert-dr-portrait-assistant'] : [])
            : array_filter([$booking->wants_concert_photography ? 'concert-photographer-p1' : null, $booking->wants_concert_videography ? 'concert-videographer' : null]);

        return collect($codes)->map(fn ($code) => CrewRole::firstOrCreate(['code' => $code], [
            'name' => self::ROLES[$code], 'event_type' => 'concert', 'is_active' => true,
        ]));
    }

    public function sync(SchedulingEvent $event, ConcertBooking $booking, ConcertBookingItemType $type): bool
    {
        $wanted = $this->roles($booking, $type)->pluck('id');
        $managed = CrewRole::whereIn('code', array_keys(self::ROLES))->pluck('id');
        $removed = $event->roleRequirements()->whereIn('crew_role_id', $managed)->whereNotIn('crew_role_id', $wanted)->delete();
        $added = false;
        foreach ($wanted as $id) {
            $added = $event->roleRequirements()->firstOrCreate(['crew_role_id' => $id], ['quantity' => 1])->wasRecentlyCreated || $added;
        }

        // Preserve custom roles, staffing quantities and every existing crew assignment for staff review.
        return $removed > 0 || $added;
    }
}
