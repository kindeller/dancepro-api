<?php

namespace App\Features\Operations\Controllers;

use App\Features\Operations\Models\AssignmentChecklistCompletion;
use App\Features\Operations\Models\ChecklistTemplate;
use App\Features\Operations\Models\ChecklistTemplateItem;
use App\Features\Operations\Models\OperationalResource;
use App\Features\Operations\Requests\ToggleChecklistItemRequest;
use App\Features\Operations\Support\OperationalRoleCodes;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrewOperationsController extends Controller
{
    public function help(): View
    {
        return view('crew.help.index', ['resources' => OperationalResource::query()->where('is_active', true)->orderBy('sort_order')->orderBy('title')->get()]);
    }

    public function assignment(Request $request, SchedulingShiftAssignment $assignment): View
    {
        $this->authoriseAssignment($request, $assignment);
        $assignment->load(['role', 'equipmentResponsibilities', 'checklistCompletions', 'coverRequests', 'timeEntry.audits', 'shift.assignments.crewProfile.user', 'shift.assignments.timeEntry', 'shift.schedulingEvent.venue', 'shift.schedulingEvent.messages.author', 'shift.schedulingEvent.messages.reads']);
        $event = $assignment->shift->schedulingEvent;
        $roleCode = $assignment->role->code;
        $roleCodes = OperationalRoleCodes::forAssignment($roleCode);
        $templates = ChecklistTemplate::query()->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('event_type')->orWhere('event_type_definition_id', $event->event_type_definition_id)->orWhere(fn ($query) => $query->whereNull('event_type_definition_id')->where('event_type', $event->event_type->value)))
            ->where(fn ($query) => $query->whereNull('role_code')->orWhereIn('role_code', $roleCodes))
            ->with('items')->get();
        $resources = OperationalResource::query()->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('event_type')->orWhere('event_type_definition_id', $event->event_type_definition_id)->orWhere(fn ($query) => $query->whereNull('event_type_definition_id')->where('event_type', $event->event_type->value)))
            ->where(fn ($query) => $query->whereNull('role_code')->orWhereIn('role_code', $roleCodes))
            ->orderBy('sort_order')->get();

        return view('crew.operations.assignment', compact('assignment', 'event', 'templates', 'resources'));
    }

    public function toggle(ToggleChecklistItemRequest $request, SchedulingShiftAssignment $assignment, ChecklistTemplateItem $item): JsonResponse|RedirectResponse
    {
        $this->authoriseAssignment($request, $assignment);
        abort_unless($this->itemApplies($assignment, $item), 404);
        AssignmentChecklistCompletion::query()->updateOrCreate(
            ['scheduling_shift_assignment_id' => $assignment->id, 'checklist_template_item_id' => $item->id],
            ['completed_by_user_id' => $request->user()->id, 'completed_at' => $request->boolean('completed') ? now() : null],
        );

        return $request->expectsJson() ? response()->json(['success' => true]) : back()->with('status', 'Checklist updated.');
    }

    private function authoriseAssignment(Request $request, SchedulingShiftAssignment $assignment): void
    {
        abort_unless($assignment->crew_profile_id === $request->user()?->crewProfile?->id && $assignment->status === 'published', 403);
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
