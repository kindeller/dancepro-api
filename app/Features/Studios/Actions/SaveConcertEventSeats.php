<?php

namespace App\Features\Studios\Actions;

use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\ConcertEventRoleSeats;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveConcertEventSeats
{
    public function execute(SchedulingEvent $event, User $user, array $roles): void
    {
        $event->loadMissing(['concertBookingItem.booking', 'roleRequirements.crewRole']);
        if ($event->concertBookingItem?->item_type !== ConcertBookingItemType::Concert) {
            throw ValidationException::withMessages(['roles' => 'Seating details are not required for dress rehearsal portraits.']);
        }

        $available = $event->roleRequirements->keyBy(fn ($requirement) => $requirement->crewRole->code);
        $booking = $event->concertBookingItem->booking;
        foreach (array_keys($roles) as $code) {
            $booked = str_contains($code, 'videographer')
                ? (bool) $booking?->wants_concert_videography
                : (str_contains($code, 'concert-photographer') && (bool) $booking?->wants_concert_photography);
            if (! $available->has($code) && $booked && $this->isSeatedRole($code)) {
                $role = CrewRole::query()->where('code', $code)->where('is_active', true)->first();
                if ($role) {
                    $requirement = $event->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
                    $requirement->setRelation('crewRole', $role);
                    $available->put($code, $requirement);
                }
            }
        }
        DB::transaction(function () use ($event, $user, $roles, $available): void {
            foreach ($roles as $code => $submitted) {
                $requirement = $available->get($code);
                if (! $requirement || ! $this->isSeatedRole($code)) {
                    throw ValidationException::withMessages(["roles.{$code}" => 'That role does not require reserved seating for this event.']);
                }
                $seats = isset($submitted['seating'])
                    ? $this->parseSeating((string) $submitted['seating'], $code)
                    : collect($submitted)->filter(fn ($seat): bool => is_array($seat))->map(fn (array $seat): array => [
                        'row' => trim((string) ($seat['row'] ?? '')),
                        'seat' => trim((string) ($seat['seat'] ?? '')),
                    ])->filter(fn (array $seat): bool => $seat['row'] !== '' || $seat['seat'] !== '')->values();
                if ($seats->contains(fn (array $seat): bool => $seat['row'] === '' || $seat['seat'] === '')) {
                    throw ValidationException::withMessages(["roles.{$code}" => 'Enter both a row and seat number for every position.']);
                }
                $minimum = str_contains($code, 'videographer') ? 5 : 1;
                $maximum = str_contains($code, 'videographer') ? 6 : 2;
                if ($seats->count() < $minimum || $seats->count() > $maximum) {
                    throw ValidationException::withMessages(["roles.{$code}" => str_contains($code, 'videographer')
                            ? 'Enter 5 or 6 video seats.' : 'Enter 1 or 2 photography seats.']);
                }
                ConcertEventRoleSeats::updateOrCreate(
                    ['scheduling_event_id' => $event->id, 'crew_role_id' => $requirement->crew_role_id],
                    ['seats' => $seats->all(), 'submitted_by_user_id' => $user->id, 'submitted_at' => now()],
                );
            }
        });
    }

    private function isSeatedRole(string $code): bool
    {
        return str_contains($code, 'concert-photographer') || str_contains($code, 'videographer');
    }

    private function parseSeating(string $value, string $code): Collection
    {
        $seats = collect(preg_split('/\R+/', trim($value)) ?: [])->filter()->flatMap(function (string $line) use ($code): array {
            if (! str_contains($line, ':')) {
                throw ValidationException::withMessages(["roles.{$code}.seating" => 'Use one line per row, for example: Row A: 12, 13, 14.']);
            }

            [$row, $numbers] = array_map('trim', explode(':', $line, 2));
            $row = preg_replace('/^row\s+/i', '', $row) ?? $row;
            $numbers = collect(preg_split('/\s*,\s*/', $numbers) ?: [])->filter();
            if ($row === '' || $numbers->isEmpty()) {
                throw ValidationException::withMessages(["roles.{$code}.seating" => 'Enter a row and at least one seat number on every line.']);
            }

            return $numbers->map(fn (string $seat): array => ['row' => $row, 'seat' => trim($seat)])->all();
        })->values();

        return $seats;
    }
}
