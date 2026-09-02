<?php

namespace App\Features\Operations\Services;

use App\Features\Operations\Models\ChecklistTemplate;
use App\Features\Operations\Support\OperationalRoleCodes;
use Illuminate\Support\Collection;

class ChecklistProgressForAssignments
{
    public function execute(Collection $assignments): array
    {
        $templates = ChecklistTemplate::query()->where('is_active', true)->with('items')->get();

        return $assignments->mapWithKeys(function ($assignment) use ($templates): array {
            $roleCode = $assignment->role->code;
            $applicable = $templates->filter(fn ($template): bool => ($template->event_type === null || ($template->event_type_definition_id !== null
                ? $template->event_type_definition_id === $assignment->shift->schedulingEvent->event_type_definition_id
                : $template->event_type === $assignment->shift->schedulingEvent->event_type->value))
                && OperationalRoleCodes::matches($template->role_code, $roleCode));
            $itemIds = $applicable->flatMap->items->pluck('id');
            $completed = $assignment->checklistCompletions->whereNotNull('completed_at')->whereIn('checklist_template_item_id', $itemIds);
            $done = $completed->pluck('checklist_template_item_id')->unique()->count();
            $completedAt = $itemIds->isNotEmpty() && $done === $itemIds->count()
                ? $completed->sortByDesc('completed_at')->first()?->completed_at
                : null;

            return [$assignment->id => ['done' => $done, 'total' => $itemIds->count(), 'completed_at' => $completedAt]];
        })->all();
    }
}
