<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Support\SchedulingEventType;
use Illuminate\Validation\ValidationException;

class UpdateAssignmentEquipment
{
    public function execute(SchedulingShiftAssignment $assignment, string $itemCode, bool $isBringing, bool $isTaking, ?string $otherNotes): void
    {
        $assignment->loadMissing(['shift.schedulingEvent', 'crewProfile']);
        $event = $assignment->shift->schedulingEvent;
        if ($itemCode !== 'media' && $event->event_type !== SchedulingEventType::Concert) {
            throw ValidationException::withMessages(['equipment' => 'Numbered video and backdrop kits are only assigned to concert work.']);
        }

        $otherNotes = filled($otherNotes) ? trim($otherNotes) : null;
        if (! $isBringing && ! $isTaking && $otherNotes === null) {
            $assignment->equipmentResponsibilities()->where('item_code', $itemCode)->delete();
        } else {
            $assignment->equipmentResponsibilities()->updateOrCreate(['item_code' => $itemCode], ['is_bringing' => $isBringing, 'is_taking' => $isTaking, 'other_notes' => $otherNotes]);
        }

        if ($assignment->status === 'published') {
            $assignment->update(['acknowledgement_status' => 'reset_by_material_change', 'acknowledged_at' => null, 'notified_at' => now()]);
            $event->update(['roster_status' => 'changed']);
            CrewNotification::query()->create(['user_id' => $assignment->crewProfile->user_id, 'type' => 'shift_changed', 'title' => 'Equipment responsibility updated', 'message' => "{$event->name}: your equipment or media responsibility changed. Please review and acknowledge the shift again."]);
        }
    }
}
