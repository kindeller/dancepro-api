<?php

namespace Database\Seeders;

use App\Features\Bookings\Actions\CreateConcertBooking;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Support\ConcertBookingRequirements;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BookingDeliveryRehearsalSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Booking rehearsal data is only available locally or in tests.');
        }
        $director = User::where('email', 'studio-director@example.test')->firstOrFail();
        DB::transaction(function () use ($director): void {
            $studio = Studio::firstOrCreate(['uuid' => '91000000-0000-4000-8000-000000000001'], [
                'name' => 'Booking Rehearsal Studio - TEST', 'code' => 'BKR', 'slug' => 'booking-rehearsal-studio',
                'status' => 'active', 'contact_name' => $director->name, 'contact_email' => $director->email,
                'description' => 'Local booking-to-delivery rehearsal. No real customers or events.',
            ]);
            StudioAccountAccess::firstOrCreate(['studio_id' => $studio->id, 'email' => $director->email], [
                'user_id' => $director->id, 'status' => 'active', 'role' => 'director', 'activated_at' => now(),
            ]);
            $venue = Venue::firstOrCreate(['uuid' => '91000000-0000-4000-8000-000000000002'], [
                'name' => 'Booking Rehearsal Theatre - TEST', 'address_line_1' => '1 Example Road',
            ]);
            $type = EventTypeDefinition::where('system_category', 'concert')->where('is_active', true)->firstOrFail();
            foreach (['mixed', 'photo-only', 'video-only'] as $variantIndex => $variant) {
                // Never reset dates, approvals, uploads or task progress when this is rerun.
                $bookingUuid = '91000000-0000-4000-8000-'.sprintf('%012d', 10 + $variantIndex);
                if (ConcertBooking::where('uuid', $bookingUuid)->exists()) {
                    continue;
                }
                $types = $variant === 'mixed' ? ['dress_rehearsal', 'concert', 'concert'] : ['concert'];
                $data = [
                    'uuid' => $bookingUuid, 'studio_name' => $studio->name, 'contact_name' => $director->name,
                    'contact_email' => $director->email, 'contact_phone' => '0400000000',
                    'wants_portrait_photography' => $variant === 'mixed',
                    'wants_concert_photography' => $variant !== 'video-only',
                    'wants_concert_videography' => $variant !== 'photo-only',
                    'approximate_family_count' => 80, 'accepted_requirements' => array_keys(ConcertBookingRequirements::all()),
                    'internal_review_note' => 'Rehearsal: '.$variant,
                    'items' => [],
                ];
                foreach ($types as $index => $itemType) {
                    $data['items'][] = [
                        'event_type_definition_id' => $type->id, 'item_type' => $itemType,
                        'title' => 'TEST '.ucfirst($variant).' '.($itemType === 'dress_rehearsal' ? 'portraits' : 'concert '.($index + 1)),
                        'venue_uuid' => $venue->uuid, 'event_date' => today()->addDays(21 + $index)->toDateString(),
                        'starts_at' => '18:00', 'finishes_at' => '20:00',
                    ];
                }
                app(CreateConcertBooking::class)->execute($data, $studio);
            }
            $this->command?->info('Rehearsal Studio Hub: '.route('customer.studio-communications.index', $studio));
            $this->command?->info('Pending test bookings: '.route('admin.concert-bookings.index'));
        });
    }
}
