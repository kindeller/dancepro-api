<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;

class ResetAssignmentAcknowledgements
{
    public function execute(SchedulingEvent $event, string $reason): void
    {
        $event->loadMissing('shifts.assignments.crewProfile');
        foreach ($event->shifts->flatMap->assignments->where('status', 'published') as $assignment) {
            $assignment->update(['acknowledgement_status' => 'reset_by_material_change', 'acknowledged_at' => null, 'notified_at' => now()]);
            CrewNotification::query()->create(['user_id' => $assignment->crewProfile->user_id, 'type' => 'shift_changed', 'title' => 'Important shift details updated', 'message' => "{$event->name}: {$reason}. Please review and acknowledge the shift again."]);
        }
        if ($event->roster_status !== 'draft') {
            $event->update(['roster_status' => 'changed']);
        }
    }
}
