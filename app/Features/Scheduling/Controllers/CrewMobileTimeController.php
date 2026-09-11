<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Scheduling\Actions\ClockInAssignment;
use App\Features\Scheduling\Actions\FinishAssignmentNow;
use App\Features\Scheduling\Actions\FinishTeamAssignments;
use App\Features\Scheduling\Actions\SaveAssignmentTime;
use App\Features\Scheduling\Models\AssignmentTimeEntry;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Requests\FinishTeamRequest;
use App\Features\Scheduling\Requests\SaveAssignmentTimeRequest;
use App\Features\Scheduling\Services\CrewMobileAssignments;
use App\Features\Scheduling\Support\SchedulingEventType;
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

    public function finishTeam(FinishTeamRequest $request, SchedulingShiftAssignment $assignment, CrewMobileAssignments $assignments, FinishTeamAssignments $finishTeam): JsonResponse
    {
        $assignment = $assignments->findOwned($request->user()->crewProfile, $assignment);
        abort_unless($assignment->is_team_leader && $assignment->shift->schedulingEvent->event_type === SchedulingEventType::Competition, 403);
        $teamAssignments = $assignment->shift->assignments()->where('status', 'published')->with(['crewProfile', 'timeEntry'])->get();
        abort_if($teamAssignments->every(fn (SchedulingShiftAssignment $teamAssignment): bool => $teamAssignment->timeEntry?->actual_finish_at !== null), 422, 'All crew are already clocked out.');
        $count = $finishTeam->execute($teamAssignments, $request->user(), $request->string('actual_finish_at')->toString(), $request->string('optional_note')->toString() ?: null);

        return ApiResponse::success('Team finish time saved.', ['updated_count' => $count]);
    }

    private function response(AssignmentTimeEntry $entry, string $message): JsonResponse
    {
        return ApiResponse::success($message, [
            'actual_clock_in_at' => $entry->actual_clock_in_at?->toIso8601String(),
            'actual_finish_at' => $entry->actual_finish_at?->toIso8601String(),
            'finish_source' => $entry->finish_source,
            'optional_note' => $entry->optional_note,
            'locked' => $entry->locked_at !== null,
        ]);
    }
}
