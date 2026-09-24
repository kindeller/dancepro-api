<?php

namespace App\Features\Programmes\Services;

use App\Features\Scheduling\Models\SchedulingEvent;

class ProgrammePhotographers
{
    public function forEvent(SchedulingEvent $event): array
    {
        abort_if($event->cancelled_at !== null, 409, 'This event is cancelled.');
        $codes = $event->event_type->value === 'competition'
            ? ['competition-photographer-p1', 'competition-photographer-p2']
            : ['concert-photographer-p1', 'concert-photographer-p2', 'photographer-p'];
        $event->load([
            'roleRequirements.crewRole', 'shifts.roleRequirements.crewRole',
            'shifts.assignments.role',
            'shifts.assignments.crewProfile' => fn ($query) => $query->select('id', 'uuid', 'user_id', 'legal_name', 'preferred_name'),
            'shifts.assignments.crewProfile.user:id,is_active',
        ]);
        $photographers = [];
        $unfilled = [];
        foreach ($event->shifts->sortBy(fn ($shift) => $shift->shift_date->format('Y-m-d').($shift->period?->value === 'morning' ? '0' : '1')) as $shift) {
            $shiftData = ['shift_uuid' => $shift->uuid, 'date' => $shift->shift_date->format('Y-m-d'), 'period' => $shift->period?->value ?? 'concert'];
            $assignments = $shift->assignments->filter(fn ($assignment) => $assignment->status === 'published'
                && in_array($assignment->role?->code, $codes, true) && $assignment->role->is_active
                && $assignment->crewProfile?->user?->is_active);
            foreach ($assignments as $assignment) {
                $crew = $assignment->crewProfile;
                $role = $assignment->role;
                $key = $role->uuid.':'.$crew->uuid;
                $photographers[$key] ??= [
                    'id' => $key, 'crew_uuid' => $crew->uuid, 'role_uuid' => $role->uuid,
                    'name' => $crew->preferred_name ?: $crew->legal_name, 'role_name' => $role->name, 'shifts' => [],
                ];
                $photographers[$key]['shifts'][] = $shiftData + ['assignment_uuid' => $assignment->uuid];
            }
            // Shift-specific quantities override the event default for that role.
            $requirements = $event->roleRequirements->keyBy('crew_role_id')
                ->replace($shift->roleRequirements->keyBy('crew_role_id'));
            foreach ($requirements as $requirement) {
                $role = $requirement->crewRole;
                if (! in_array($role?->code, $codes, true)) {
                    continue;
                }
                $assigned = $assignments->where('crew_role_id', $role->id)->pluck('crew_profile_id')->unique()->count();
                $missing = max(0, $requirement->quantity - $assigned);
                if ($missing > 0) {
                    $unfilled[] = $shiftData + ['role_name' => $role->name, 'missing_count' => $missing];
                }
            }
        }

        return [
            'event_uuid' => $event->uuid, 'checked_at' => now()->toIso8601String(),
            'has_shifts' => $event->shifts->isNotEmpty(),
            'photographers' => array_values($photographers), 'unfilled' => $unfilled,
        ];
    }
}
