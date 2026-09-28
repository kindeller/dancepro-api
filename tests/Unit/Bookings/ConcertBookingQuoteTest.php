<?php

namespace Tests\Unit\Bookings;

use App\Features\Bookings\Services\ConcertBookingQuote;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConcertBookingQuoteTest extends TestCase
{
    #[DataProvider('videoCases')]
    public function test_video_floor_and_family_rate(int $concerts, int $families, int $expected): void
    {
        $quote = app(ConcertBookingQuote::class)->calculate([
            'items' => array_fill(0, $concerts, ['item_type' => 'concert']),
            'wants_concert_videography' => true,
            'approximate_family_count' => $families,
        ]);
        $this->assertSame($expected, $quote['video_quote_cents']);
        $this->assertNull($quote['photo_minimum_cents']);
        $this->assertStringContainsString('include GST', $quote['gst_note']);
    }

    public static function videoCases(): array
    {
        return [
            'one below' => [1, 99, 150000], 'one boundary' => [1, 100, 150000], 'one above' => [1, 101, 151500],
            'two below' => [2, 199, 300000], 'two boundary' => [2, 200, 300000], 'two above' => [2, 201, 301500],
            'three below' => [3, 299, 450000], 'three boundary' => [3, 300, 450000], 'three above' => [3, 301, 451500],
            'four concerts' => [4, 450, 675000],
        ];
    }

    #[DataProvider('photoCases')]
    public function test_photo_minimum_counts_booked_photo_events(int $concerts, int $portraits, bool $photos, int $expected): void
    {
        $quote = app(ConcertBookingQuote::class)->calculate([
            'items' => [...array_fill(0, $concerts, ['item_type' => 'concert']), ...array_fill(0, $portraits, ['item_type' => 'dress_rehearsal'])],
            'wants_concert_photography' => $photos,
        ]);
        $this->assertSame($expected, $quote['photo_minimum_cents']);
        $this->assertNull($quote['video_quote_cents']);
    }

    public static function photoCases(): array
    {
        return [
            'one concert' => [1, 0, true, 80000], 'two concerts' => [2, 0, true, 140000],
            'portrait and concert' => [1, 1, true, 140000], 'portrait and two concerts' => [2, 1, true, 200000],
            'portrait only with video-only concert' => [1, 1, false, 80000],
        ];
    }
}
