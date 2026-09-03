<?php

namespace App\Features\Operations\Services;

use App\Features\Operations\Models\ChecklistTemplate;
use App\Features\Operations\Support\OperationalRoleCodes;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use Illuminate\Support\Collection;

class ChecklistItemsForAssignment
{
    public function get(SchedulingShiftAssignment $assignment): Collection
    {
        $assignment->loadMissing(['role', 'shift.schedulingEvent', 'checklistCompletions']);
        $event = $assignment->shift->schedulingEvent;
        $roleCodes = OperationalRoleCodes::forAssignment($assignment->role->code);
        $completions = $assignment->checklistCompletions->keyBy('checklist_template_item_id');

        return ChecklistTemplate::query()->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('event_type')
                ->orWhere('event_type_definition_id', $event->event_type_definition_id)
                ->orWhere(fn ($query) => $query->whereNull('event_type_definition_id')->where('event_type', $event->event_type->value)))
            ->where(fn ($query) => $query->whereNull('role_code')->orWhereIn('role_code', $roleCodes))
            ->with('items')->get()->flatMap->items
            ->sortBy('sort_order')->values()
            ->map(function ($item) use ($completions): array {
                $completion = $completions->get($item->id);

                return [
                    'id' => $item->uuid,
                    'instruction' => $item->instruction,
                    'completed' => $completion?->completed_at !== null,
                    'completed_at' => $completion?->completed_at?->toIso8601String(),
                ];
            });
    }
}
