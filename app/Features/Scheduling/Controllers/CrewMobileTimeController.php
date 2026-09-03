<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Scheduling\Actions\ClockInAssignment;
use App\Features\Scheduling\Actions\FinishAssignmentNow;
use App\Features\Scheduling\Actions\SaveAssignmentTime;
use App\Features\Scheduling\Models\AssignmentTimeEntry;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Requests\SaveAssignmentTimeRequest;
use App\Features\Scheduling\Services\CrewMobileAssignments;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileTimeController extends Controller
{
    public function clockIn(Request $request, SchedulingShiftAssignment $assignment, CrewMobileAssignments $assignments, ClockInAssignment $clockIn): JsonResponse
    {
        $assignment = $assignments->findOwned($request->user()->crewProfile, $assignment);

        if ($assignment->timeEntry?->actual_clock_in_at !== null) {
            return $this->response($assignment->timeEntry, 'Clock-in already recorded.');
        }

        return $this->response($clockIn->execute($assignment, $request->user()), 'Clock-in recorded.');
    }

    public function finish(Request $request, SchedulingShiftAssignment $assignment, CrewMobileAssignments $assignments, FinishAssignmentNow $finish): JsonResponse
    {
        $assignment = $assignments->findOwned($request->user()->crewProfile, $assignment);

        if ($assignment->timeEntry?->actual_finish_at !== null) {
            return $this->response($assignment->timeEntry, 'Clock-out already recorded.');
        }

        return $this->response($finish->execute($assignment, $request->user()), 'Clock-out recorded.');
    }

    public function update(SaveAssignmentTimeRequest $request, SchedulingShiftAssignment $assignment, CrewMobileAssignments $assignments, SaveAssignmentTime $save): JsonResponse
    {
        $assignment = $assignments->findOwned($request->user()->crewProfile, $assignment);
        $entry = $save->execute($assignment, $request->user(), $request->input('actual_clock_in_at'), $request->input('actual_finish_at'), $request->input('optional_note'), 'crew');

        return $this->response($entry, 'Times saved.');
    }

    private function response(AssignmentTimeEntry $entry, string $message): JsonResponse
    {
        return ApiResponse::success($message, [
            'actual_clock_in_at' => $entry->actual_clock_in_at?->toIso8601String(),
            'actual_finish_at' => $entry->actual_finish_at?->toIso8601String(),
            'optional_note' => $entry->optional_note,
            'locked' => $entry->locked_at !== null,
        ]);
    }
}
