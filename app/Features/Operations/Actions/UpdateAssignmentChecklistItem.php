<?php

namespace App\Features\Operations\Actions;

use App\Features\Operations\Models\AssignmentChecklistCompletion;
use App\Features\Operations\Models\ChecklistTemplateItem;
use App\Features\Operations\Support\OperationalRoleCodes;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Models\User;

class UpdateAssignmentChecklistItem
{
    public function execute(SchedulingShiftAssignment $assignment, ChecklistTemplateItem $item, User $user, bool $completed): void
    {
        abort_unless($assignment->crew_profile_id === $user->crewProfile?->id && $assignment->status === 'published', 404);
        abort_unless($this->itemApplies($assignment, $item), 404);

        AssignmentChecklistCompletion::query()->updateOrCreate(
            ['scheduling_shift_assignment_id' => $assignment->id, 'checklist_template_item_id' => $item->id],
            ['completed_by_user_id' => $user->id, 'completed_at' => $completed ? now() : null],
        );
    }

    private function itemApplies(SchedulingShiftAssignment $assignment, ChecklistTemplateItem $item): bool
    {
        $item->loadMissing('template');
        $assignment->loadMissing(['role', 'shift.schedulingEvent']);

        return $item->template->is_active
            && ($item->template->event_type === null || ($item->template->event_type_definition_id !== null
                ? $item->template->event_type_definition_id === $assignment->shift->schedulingEvent->event_type_definition_id
                : $item->template->event_type === $assignment->shift->schedulingEvent->event_type->value))
            && OperationalRoleCodes::matches($item->template->role_code, $assignment->role->code);
    }
}
