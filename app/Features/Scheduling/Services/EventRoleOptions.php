<?php

namespace App\Features\Scheduling\Services;

use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Collection;

class EventRoleOptions
{
    private const CONCERT_SLOTS = ['concert-videographer' => 0, 'concert-photographer-p1' => 1, 'concert-photographer-p2' => 2];

    public function forEvent(SchedulingEvent $event, ?Collection $roles = null): Collection
    {
        $roles ??= CrewRole::where('is_active', true)->orderBy('name')->get();
        $event->loadMissing(['roleRequirements.crewRole', 'concertBookingItem']);
        $concert = $event->event_type->value === 'concert' && ! $this->isPortraits($event);

        return $roles->filter(fn (CrewRole $role) => $role->is_active
            && (! $concert || isset(self::CONCERT_SLOTS[$role->code]))
            && ($role->event_type === null || $role->event_type === $event->event_type->value)
            && ($event->event_type_definition_id === null || $role->event_type_definition_id === null || $role->event_type_definition_id === $event->event_type_definition_id)
            && ! $event->roleRequirements->contains('crew_role_id', $role->id))->values();
    }

    public function slots(SchedulingEvent $event): Collection
    {
        $event->loadMissing(['roleRequirements.crewRole', 'concertBookingItem']);
        $requirements = $event->roleRequirements->sortBy('id')->values();
        if ($event->event_type->value !== 'concert' || $this->isPortraits($event)) {
            return $requirements;
        }
        $slots = collect([null, null, null]);
        $positions = self::CONCERT_SLOTS;
        foreach ($requirements as $requirement) {
            if (isset($positions[$requirement->crewRole->code])) {
                $slots->put($positions[$requirement->crewRole->code], $requirement);
            }
        }
        foreach ($requirements as $requirement) {
            if (! isset($positions[$requirement->crewRole->code])) {
                $empty = $slots->search(fn ($slot) => $slot === null);
                if ($empty !== false) {
                    $slots->put($empty, $requirement);
                }
            }
        }

        return $slots;
    }

    private function isPortraits(SchedulingEvent $event): bool
    {
        return $event->concertBookingItem?->item_type->value === 'dress_rehearsal'
            || $event->roleRequirements->contains(fn ($requirement) => $requirement->crewRole->code === 'photographer-p');
    }
}
