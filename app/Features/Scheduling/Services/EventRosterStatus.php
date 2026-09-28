<?php

namespace App\Features\Scheduling\Services;

use App\Features\Scheduling\Models\SchedulingEvent;

class EventRosterStatus
{
    public function forEvent(SchedulingEvent $event): array
    {
        $ready = ! $event->cancelled_at && $event->rosterIsReady();
        $label = $event->cancelled_at ? 'Cancelled' : ($ready ? 'READY' : (in_array($event->roster_status, ['published', 'changed'], true) ? 'Assigned' : match ($event->availability_status->value) {
            'open' => 'Availability open', 'closed' => 'Rostering', default => 'Draft',
        }));
        $crew = collect();
        if ($ready) {
            $event->loadMissing(['shifts.assignments.role', 'shifts.assignments.crewProfile.user']);
            $crew = $event->shifts->sortBy('starts_at')->flatMap->assignments
                ->map(fn ($assignment) => $this->roleLabel($assignment->role->code, $assignment->role->name).': '.($assignment->crewProfile->preferred_name ?: $assignment->crewProfile->user->name))->unique()->values();
        }

        return ['ready' => $ready, 'label' => $label, 'crew' => $crew];
    }

    private function roleLabel(string $code, string $name): string
    {
        return match ($code) {
            'concert-videographer' => 'V', 'concert-photographer-p1' => 'P1', 'concert-photographer-p2' => 'P2', 'photographer-p' => 'P', 'concert-dr-portrait-assistant' => 'A',
            default => $name,
        };
    }
}
