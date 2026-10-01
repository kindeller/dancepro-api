<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Scheduling\Actions\AddEventRole;
use App\Features\Scheduling\Actions\ChangeEventWorkflow;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Requests\AddEventRoleRequest;
use App\Features\Scheduling\Requests\ChangeEventWorkflowRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AdminEventWorkflowController extends Controller
{
    public function addRole(AddEventRoleRequest $request, SchedulingEvent $schedulingEvent, AddEventRole $add): JsonResponse
    {
        $add->execute($schedulingEvent, $request->validated('role_uuid'));

        return response()->json(['success' => true, 'message' => 'Role added.', 'data' => []]);
    }

    public function changeStatus(ChangeEventWorkflowRequest $request, SchedulingEvent $schedulingEvent, ChangeEventWorkflow $change): JsonResponse
    {
        $change->execute($schedulingEvent, $request->validated());

        return response()->json(['success' => true, 'message' => 'Event status updated.', 'data' => []]);
    }
}
