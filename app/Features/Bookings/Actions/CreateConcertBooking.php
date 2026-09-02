<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Venues\Models\Venue;
use Illuminate\Support\Facades\DB;

class CreateConcertBooking
{
    public function execute(array $data): ConcertBooking
    {
        return DB::transaction(function () use ($data): ConcertBooking {
            $booking = ConcertBooking::query()->create(collect($data)->except('items')->all());
            $items = collect($data['items'])->map(function (array $item): array {
                $venueUuid = $item['venue_uuid'];
                unset($item['venue_uuid']);

                if ($venueUuid === 'other') {
                    $item['venue_id'] = null;

                    return $item;
                }

                $venue = Venue::query()->where('uuid', $venueUuid)->firstOrFail();
                $item['venue_id'] = $venue->id;
                $item['venue_name'] = $venue->name;
                $item['venue_address'] = collect([
                    $venue->address_line_1,
                    $venue->address_line_2,
                    trim(collect([$venue->suburb, $venue->state, $venue->postcode])->filter()->join(' ')),
                ])->filter()->join(', ');

                return $item;
            })->all();
            $booking->items()->createMany($items);

            return $booking;
        });
    }
}
