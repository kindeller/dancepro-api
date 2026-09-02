<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Venues\Models\Venue;
use Illuminate\Support\Facades\DB;

class CreateConcertBooking
{
    public function execute(array $data): ConcertBooking
    {
        $data = collect($data)->except('website')->all();
        $fingerprint = $this->fingerprint($data);

        return DB::transaction(function () use ($data, $fingerprint): ConcertBooking {
            $duplicate = ConcertBooking::query()
                ->where('submission_fingerprint', $fingerprint)
                ->where('created_at', '>=', now()->subMinutes((int) config('concerts.booking_duplicate_window_minutes')))
                ->latest('id')
                ->first();

            if ($duplicate) {
                return $duplicate;
            }

            $booking = ConcertBooking::query()->create([
                ...collect($data)->except('items')->all(),
                'submission_fingerprint' => $fingerprint,
            ]);
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

    private function fingerprint(array $data): string
    {
        return hash('sha256', json_encode($this->normalise($data), JSON_THROW_ON_ERROR));
    }

    private function normalise(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(function (mixed $item): mixed {
            if (is_array($item)) {
                return $this->normalise($item);
            }

            return is_string($item) ? mb_strtolower(trim($item)) : $item;
        }, $value);
    }
}
