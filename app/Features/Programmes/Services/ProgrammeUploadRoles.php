<?php

namespace App\Features\Programmes\Services;

use App\Features\Crew\Models\CrewRole;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;

class ProgrammeUploadRoles
{
    public function forEvent(SchedulingEvent $event, ?User $actor = null): array
    {
        abort_if($event->cancelled_at !== null, 409, 'This event is cancelled.');
        $event->load(['roleRequirements', 'shifts.roleRequirements', 'shifts.assignments.crewProfile:id,user_id']);
        $competition = $event->event_type->value === 'competition';
        $codes = $competition ? ['competition-photographer-p1', 'competition-photographer-p2', 'competition-videographer']
            : ['concert-photographer-p1', 'concert-photographer-p2', 'photographer-p', 'concert-videographer', 'videographer'];
        $existingRoleIds = ProgrammePhotoContribution::whereIn('programme_upload_plan_id', ProgrammeUploadPlan::whereIn('programme_id', Programme::where('scheduling_event_id', $event->id)->select('id'))->select('id'))->pluck('crew_role_id');
        $plannedIds = $event->roleRequirements->pluck('crew_role_id')->merge($existingRoleIds);
        foreach ($event->shifts as $shift) {
            $plannedIds = $plannedIds->merge($shift->roleRequirements->pluck('crew_role_id'))->merge($shift->assignments->pluck('crew_role_id'));
        }
        $roles = [];
        $defaults = [];
        foreach (CrewRole::whereIn('id', $plannedIds->unique())->whereIn('code', $codes)->where('is_active', true)->orderBy('code')->get() as $role) {
            $shifts = [];
            foreach ($event->shifts as $shift) {
                $required = $shift->roleRequirements->firstWhere('crew_role_id', $role->id)
                    ?? $event->roleRequirements->firstWhere('crew_role_id', $role->id);
                $assigned = $shift->assignments->where('crew_role_id', $role->id);
                if (($required && $required->quantity > 0) || $assigned->isNotEmpty() || $existingRoleIds->contains($role->id)) {
                    $shifts[] = ['assignment_uuid' => $shift->uuid, 'shift_uuid' => $shift->uuid,
                        'date' => $shift->shift_date->format('Y-m-d'), 'period' => $shift->period?->value ?? 'concert'];
                    if (! $competition && $actor && $assigned->contains(fn ($a) => $a->status === 'published' && $a->crewProfile?->user_id === $actor->id)) {
                        $defaults[] = $role->uuid;
                    }
                }
            }
            $roles[] = ['id' => $role->uuid, 'role_uuid' => $role->uuid, 'name' => $role->name,
                'role_name' => $role->name, 'media_type' => str_contains($role->code, 'videographer') ? 'video' : 'photo', 'shifts' => $shifts];
        }
        $defaults = array_values(array_unique($defaults));

        return ['event_uuid' => $event->uuid, 'checked_at' => now()->toIso8601String(), 'has_shifts' => $event->shifts->isNotEmpty(),
            'roles' => $roles, 'photographers' => $roles, 'identity_version' => 2, 'default_role_uuid' => count($defaults) === 1 ? $defaults[0] : null,
            'unfilled' => app(ProgrammePhotographers::class)->forEvent($event)['unfilled']];
    }
}
