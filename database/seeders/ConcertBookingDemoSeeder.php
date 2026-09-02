<?php

namespace Database\Seeders;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Support\ConcertBookingStatus;
use Illuminate\Database\Seeder;
use RuntimeException;

class ConcertBookingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('ConcertBookingDemoSeeder may only run locally.');
        }

        $this->booking('70000000-0000-4000-8000-000000000001', [
            'studio_name' => 'Fictional Harbour Dance Academy',
            'contact_name' => 'Taylor Example',
            'contact_email' => 'taylor@harbour-dance.example.test',
            'contact_phone' => '0400 100 001',
            'portrait_photography_interest' => 'yes',
            'wants_portrait_photography' => true,
            'wants_concert_photography' => true,
            'wants_concert_videography' => true,
            'concert_inclusions' => ['presentations_awards', 'tap_dance', 'video_display'],
            'approximate_family_count' => 185,
            'postal_address' => '10 Example Parade, Fremantle WA 6160',
            'previous_video_feedback' => 'We liked the wide stage coverage and would like more backstage footage this year.',
        ], [
            ['uuid' => '71000000-0000-4000-8000-000000000001', 'item_type' => 'dress_rehearsal', 'title' => null, 'venue_name' => 'Fictional Harbour Arts Centre', 'venue_address' => '10 Example Parade, Fremantle WA', 'event_date' => now()->addMonths(2)->toDateString(), 'starts_at' => '16:00', 'finishes_at' => '20:00'],
            ['uuid' => '71000000-0000-4000-8000-000000000002', 'item_type' => 'concert', 'title' => 'Dancing by the Harbour', 'venue_name' => 'Fictional Harbour Arts Centre', 'venue_address' => '10 Example Parade, Fremantle WA', 'event_date' => now()->addMonths(2)->addDays(2)->toDateString(), 'starts_at' => '18:30', 'finishes_at' => '21:30'],
        ]);

        $this->booking('70000000-0000-4000-8000-000000000002', [
            'studio_name' => 'Fictional Northern Stars Dance',
            'contact_name' => 'Jordan Sample',
            'contact_email' => 'jordan@northern-stars.example.test',
            'contact_phone' => '0400 100 002',
            'portrait_photography_interest' => 'no',
            'wants_portrait_photography' => false,
            'wants_concert_photography' => true,
            'wants_concert_videography' => false,
            'concert_inclusions' => ['presentations_awards', 'unamplified_singing'],
            'approximate_family_count' => 95,
        ], [
            ['uuid' => '71000000-0000-4000-8000-000000000003', 'item_type' => 'concert', 'title' => 'Northern Lights', 'venue_name' => 'Fictional Lakes Theatre', 'venue_address' => '25 Sample Road, Joondalup WA', 'event_date' => now()->addMonths(3)->toDateString(), 'starts_at' => '17:00', 'finishes_at' => '19:30'],
        ]);

        $this->booking('70000000-0000-4000-8000-000000000003', [
            'studio_name' => 'Fictional Hills Performing Arts',
            'contact_name' => 'Casey Placeholder',
            'contact_email' => 'casey@hills-performing.example.test',
            'contact_phone' => '0400 100 003',
            'portrait_photography_interest' => 'unsure',
            'wants_portrait_photography' => false,
            'wants_concert_photography' => true,
            'wants_concert_videography' => true,
            'concert_inclusions' => ['tap_dance'],
            'multiple_concert_relationship' => 'different_attend_all',
            'approximate_family_count' => 240,
            'postal_address' => '8 Placeholder Way, Kalamunda WA 6076',
            'previous_video_feedback' => 'This is a first-time DancePro booking. Please contact us about portrait options.',
        ], [
            ['uuid' => '71000000-0000-4000-8000-000000000004', 'item_type' => 'concert', 'title' => 'Junior Showcase', 'venue_name' => 'Fictional Hills Auditorium', 'venue_address' => '8 Placeholder Way, Kalamunda WA', 'event_date' => now()->addMonths(4)->toDateString(), 'starts_at' => '11:00', 'finishes_at' => '13:00'],
            ['uuid' => '71000000-0000-4000-8000-000000000005', 'item_type' => 'concert', 'title' => 'Senior Showcase', 'venue_name' => 'Fictional Hills Auditorium', 'venue_address' => '8 Placeholder Way, Kalamunda WA', 'event_date' => now()->addMonths(4)->toDateString(), 'starts_at' => '18:00', 'finishes_at' => '21:00'],
        ]);

        $this->command?->info('Three fictional concert booking responses were seeded.');
    }

    private function booking(string $uuid, array $attributes, array $items): void
    {
        $booking = ConcertBooking::query()->updateOrCreate(['uuid' => $uuid], [
            'status' => ConcertBookingStatus::Pending,
            'accepted_requirements' => ['accurate', 'portrait_space', 'no_personal_backdrop_photos', 'minimum_spend', 'photographer_seat', 'no_audience_recording', 'promote_gallery', 'credit_images', 'video_access', 'video_space_audio', 'programme_invoice'],
            ...$attributes,
        ]);

        foreach ($items as $item) {
            $booking->items()->updateOrCreate(['uuid' => $item['uuid']], $item);
        }
    }
}
