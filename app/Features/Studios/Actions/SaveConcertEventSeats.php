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
        return collect(preg_split('/\R+/', trim($value)) ?: [])->filter()->flatMap(function (string $line) use ($code): array {
            $line = str_replace(['–', '—'], '-', trim($line));
            if (str_contains($line, ':')) {
                [$row, $numbers] = array_map('trim', explode(':', $line, 2));
                $row = preg_replace('/^row\s+/i', '', $row) ?? $row;
            } elseif (preg_match('/^([a-z]+)\s*(\d.*)$/i', $line, $parts)) {
                [, $row, $numbers] = $parts;
            } else {
                throw ValidationException::withMessages(["roles.{$code}.seating" => 'Enter seats like J16-20 or J16, 18. Use a new line for another row.']);
            }

            $row = mb_strtoupper(trim($row));
            if ($row === '' || trim($numbers) === '') {
                throw ValidationException::withMessages(["roles.{$code}.seating" => 'Enter a row letter and seat numbers, for example J16-20.']);
            }

            return collect(explode(',', $numbers))->flatMap(function (string $number) use ($row, $code): array {
                $number = trim($number);
                if (preg_match('/^(\d{1,9})\s*-\s*([a-z]*)(\d{1,9})$/i', $number, $range)) {
                    $start = (int) $range[1];
                    $end = (int) $range[3];
                    if ($end < $start || $end - $start >= 6 || ($range[2] !== '' && mb_strtoupper($range[2]) !== $row)) {
                        throw ValidationException::withMessages(["roles.{$code}.seating" => 'Use an increasing range of up to 6 seats in the same row, for example J16-20.']);
                    }

                    return array_map(fn (int $seat): array => ['row' => $row, 'seat' => (string) $seat], range($start, $end));
                }
                if ($number === '' || preg_match('/^\d+\s*-/', $number)) {
                    throw ValidationException::withMessages(["roles.{$code}.seating" => 'Check the seat numbers. Use J16-20 for a range, or J16, 18 for separate seats.']);
                }

                return [['row' => $row, 'seat' => ctype_digit($number) ? ltrim($number, '0') ?: '0' : $number]];
            })->all();
        })->unique(fn (array $seat): string => $seat['row'].'|'.mb_strtoupper($seat['seat']))->values();
    }
}
