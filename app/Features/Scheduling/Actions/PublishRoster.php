<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishRoster
{
    public function execute(SchedulingEvent $event): void
    {
        $event->load(['roleRequirements', 'shifts.assignments.crewProfile']);
        foreach ($event->shifts as $shift) {
            $missing = $event->roleRequirements->pluck('crew_role_id')->diff($shift->assignments->pluck('crew_role_id'));
            if ($missing->isNotEmpty()) {
                $shiftLabel = $shift->period?->value ?? 'concert';
                throw ValidationException::withMessages(['roster' => "Complete every required role for {$shift->shift_date->format('D j M')} {$shiftLabel} before publishing."]);
            }
        }

        DB::transaction(function () use ($event): void {
            foreach ($event->shifts->flatMap->assignments as $assignment) {
                $assignment->update(['status' => 'published', 'acknowledgement_status' => 'not_acknowledged', 'acknowledged_at' => null, 'published_at' => now(), 'notified_at' => now()]);
                $assignment->loadMissing(['crewProfile.user', 'shift', 'role']);
                CrewNotification::query()->create(['user_id' => $assignment->crewProfile->user_id, 'type' => 'shift_allocation', 'title' => 'New shift allocation', 'message' => "{$event->name}: {$assignment->role->name} on {$assignment->shift->shift_date->format('D j M')}. Please acknowledge the shift."]);
                $assignment->shift->availabilityResponses()->where('crew_profile_id', $assignment->crew_profile_id)->update(['locked_at' => now()]);
            }
            $event->update(['availability_status' => 'closed', 'roster_status' => 'published', 'roster_published_at' => now()]);
        });
    }
}
