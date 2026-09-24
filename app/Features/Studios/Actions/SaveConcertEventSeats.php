<?php

namespace App\Features\Studios\Actions;

use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\ConcertEventRoleSeats;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveConcertEventSeats
{
    public function execute(SchedulingEvent $event, User $user, array $roles): void
    {
        $event->loadMissing(['concertBookingItem', 'roleRequirements.crewRole']);
        if ($event->concertBookingItem?->item_type !== ConcertBookingItemType::Concert) {
            throw ValidationException::withMessages(['roles' => 'Seating details are not required for dress rehearsal portraits.']);
        }

        $available = $event->roleRequirements->keyBy(fn ($requirement) => $requirement->crewRole->code);
        DB::transaction(function () use ($event, $user, $roles, $available): void {
            foreach ($roles as $code => $submitted) {
                $requirement = $available->get($code);
                if (! $requirement || ! $this->isSeatedRole($code)) {
                    throw ValidationException::withMessages(["roles.{$code}" => 'That role does not require reserved seating for this event.']);
                }
                $seats = collect($submitted)->map(fn (array $seat): array => [
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
}
