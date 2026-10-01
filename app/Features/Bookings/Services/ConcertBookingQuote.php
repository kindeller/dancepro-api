<?php

namespace App\Features\Bookings\Services;

class ConcertBookingQuote
{
    public const VIDEO_CONCERT_RATE_CENTS = 150000;

    public const VIDEO_FAMILY_RATE_CENTS = 1500;

    public function calculate(array $details): array
    {
        $items = collect($details['items']);
        $concerts = $items->where('item_type', 'concert')->count();
        $photoEvents = $items->where('item_type', 'dress_rehearsal')->count()
            + (! empty($details['wants_concert_photography']) ? $concerts : 0);
        $videoConcerts = ! empty($details['wants_concert_videography']) ? $concerts : 0;
        $families = (int) ($details['final_family_count'] ?? $details['approximate_family_count'] ?? 0);

        return [
            'currency' => 'AUD',
            'photo_events' => $photoEvents,
            'photo_minimum_cents' => $photoEvents > 0 ? 80000 + ($photoEvents - 1) * 60000 : null,
            'video_concerts' => $videoConcerts,
            'families' => $families,
            'families_confirmed' => isset($details['final_family_count']),
            'video_quote_cents' => $videoConcerts > 0 && $families > 0
                ? max($videoConcerts * self::VIDEO_CONCERT_RATE_CENTS, $families * self::VIDEO_FAMILY_RATE_CENTS) : null,
            'gst_note' => 'All amounts are in AUD and include GST.',
        ];
    }
}
