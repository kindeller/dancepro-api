<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Venues\Models\Venue;
use Illuminate\Validation\ValidationException;

class ResolveConcertBookingVenue
{
    public function execute(ConcertBookingItem $item, string $action, ?string $venueUuid): Venue
    {
        if ($item->approval_status !== 'pending') {
            throw ValidationException::withMessages(['venue' => 'Only pending booking events can have their venue resolved.']);
        }

        $venue = $action === 'match'
            ? Venue::query()->where('uuid', $venueUuid)->firstOrFail()
            : Venue::query()->create([
                'name' => $item->venue_name,
                'address_line_1' => $item->venue_address,
                'state' => 'WA',
            ]);

        $item->update(['venue_id' => $venue->id]);

        return $venue;
    }
}
