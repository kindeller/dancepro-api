<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Operations\Actions\UpdateAssignmentChecklistItem;
use App\Features\Operations\Models\ChecklistTemplateItem;
use App\Features\Operations\Requests\ToggleChecklistItemRequest;
use App\Features\Scheduling\Actions\AcknowledgeShiftAssignment;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Services\CrewMobileAssignments;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileAssignmentActionController extends Controller
{
    public function acknowledge(Request $request, SchedulingShiftAssignment $assignment, CrewMobileAssignments $assignments, AcknowledgeShiftAssignment $acknowledge): JsonResponse
    {
        $assignment = $assignments->findOwned($request->user()->crewProfile, $assignment);
        $acknowledge->execute($assignment, $request->user()->crewProfile);

        return ApiResponse::success('Assignment acknowledged.');
    }

    public function checklist(ToggleChecklistItemRequest $request, SchedulingShiftAssignment $assignment, ChecklistTemplateItem $item, UpdateAssignmentChecklistItem $update): JsonResponse
    {
        $update->execute($assignment, $item, $request->user(), $request->boolean('completed'));

        return ApiResponse::success('Checklist updated.');
    }
}
