<?php

namespace App\Features\Studios\Actions;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\StudioBookingChangeRequest;
use App\Features\Tracking\Models\ConcertTrackerAlert;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateStudioBookingChange
{
    public function execute(SchedulingEvent $event, User $requestedBy, array $data): StudioBookingChangeRequest
    {
        if ($event->cancelled_at) {
            throw ValidationException::withMessages(['booking' => 'This event has been cancelled and cannot receive a change request.']);
        }
        $data = isset($data['booking']) ? $this->bookingChanges($event, $data) : $data;

        return DB::transaction(function () use ($event, $requestedBy, $data): StudioBookingChangeRequest {
            $change = StudioBookingChangeRequest::create($data + [
                'scheduling_event_id' => $event->id,
                'requested_by_user_id' => $requestedBy->id,
                'status' => 'requested',
            ]);

            User::query()->where('is_admin', true)->where('is_active', true)->each(function (User $user) use ($change, $event): void {
                ConcertTrackerAlert::create([
                    'user_id' => $user->id,
                    'scheduling_event_id' => $event->id,
                    'type' => 'studio_booking_change',
                    'dedupe_key' => 'studio-booking-change:'.$change->id.':'.$user->id,
                    'title' => 'Booking change requested',
                    'body' => $event->name.' · '.str($change->change_type)->replace('_', ' ')->title(),
                    'url' => route('admin.scheduling-events.show', $event),
                ]);
            });

            return $change;
        });
    }

    private function bookingChanges(SchedulingEvent $event, array $data): array
    {
        $event->loadMissing(['concertBookingItem.booking', 'shifts', 'venue']);
        $item = $event->concertBookingItem;
        $booking = $item?->booking;
        $submitted = $data['booking'];
        $isConcert = $item?->item_type?->value === 'concert';
        $currentServices = collect([
            $isConcert && $booking?->wants_concert_photography ? 'photography' : null,
            $isConcert && $booking?->wants_concert_videography ? 'videography' : null,
            ! $isConcert && $booking?->wants_portrait_photography ? 'portraits' : null,
        ])->filter()->sort()->values();
        $requestedServices = collect($submitted['services'] ?? [])->sort()->values();
        $startLabel = $isConcert ? 'Curtain up' : 'Shoot time';
        $current = [
            'Date' => $event->event_date?->format('Y-m-d'),
            'Venue' => $item?->venue_name ?: $event->venue?->name,
            $startLabel => $item?->starts_at ? Carbon::parse($item->starts_at)->format('H:i') : null,
            'Expected finish' => $item?->finishes_at ? Carbon::parse($item->finishes_at)->format('H:i') : null,
            'Venue access / setup' => $event->shifts->sortBy('posted_arrival_at')->first()?->posted_arrival_at?->format('H:i'),
            'Services' => $currentServices->join(', '),
        ];
        $requested = collect([
            'event_date' => 'Date',
            'venue_name' => 'Venue',
            'starts_at' => $startLabel,
            'finishes_at' => 'Expected finish',
            'access_time' => 'Venue access / setup',
        ])->filter(fn (string $label, string $key): bool => array_key_exists($key, $submitted))
            ->mapWithKeys(fn (string $label, string $key): array => [$label => $submitted[$key]])
            ->when(array_key_exists('services_present', $submitted), fn ($values) => $values->put('Services', $requestedServices->join(', ')))
            ->all();
        $changes = collect($requested)->filter(fn ($value, string $label): bool => (string) $value !== (string) $current[$label]);
        if ($changes->isEmpty()) {
            throw ValidationException::withMessages(['booking' => 'Change at least one booking detail before submitting the request.']);
        }

        return [
            'change_type' => 'booking_details',
            'requested_fields' => $submitted,
            'requested_value' => $changes->map(fn ($value, string $label): string => $label.': '.($current[$label] ?: 'not set').' → '.($value ?: 'remove'))->join("\n"),
            'details' => $data['details'] ?? null,
        ];
    }
}
