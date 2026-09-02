<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Scheduling\Actions\ClockInAssignment;
use App\Features\Scheduling\Actions\FinishAssignmentNow;
use App\Features\Scheduling\Actions\FinishTeamAssignments;
use App\Features\Scheduling\Actions\SaveAssignmentTime;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Requests\FinishTeamRequest;
use App\Features\Scheduling\Requests\SaveAssignmentTimeRequest;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CrewTimeController extends Controller
{
    public function clockIn(Request $request, SchedulingShiftAssignment $assignment, ClockInAssignment $clockIn): RedirectResponse
    {
        $this->authoriseAssignment($request, $assignment);
        $clockIn->execute($assignment, $request->user());

        return back()->with('status', 'Clock-in recorded.');
    }

    public function update(SaveAssignmentTimeRequest $request, SchedulingShiftAssignment $assignment, SaveAssignmentTime $saveTime): RedirectResponse
    {
        $this->authoriseAssignment($request, $assignment);
        $saveTime->execute($assignment, $request->user(), $request->input('actual_clock_in_at'), $request->input('actual_finish_at'), $request->input('optional_note'), 'crew');

        return back()->with('status', 'Times saved.');
    }

    public function finish(Request $request, SchedulingShiftAssignment $assignment, FinishAssignmentNow $finish): RedirectResponse
    {
        $this->authoriseAssignment($request, $assignment);
        $finish->execute($assignment, $request->user());

        return back()->with('status', 'Clock-out recorded.');
    }

    public function finishTeam(FinishTeamRequest $request, SchedulingShiftAssignment $assignment, FinishTeamAssignments $finishTeam): RedirectResponse
    {
        $this->authoriseAssignment($request, $assignment);
        $assignment->loadMissing('shift.schedulingEvent');
        abort_unless($assignment->is_team_leader && $assignment->shift->schedulingEvent->event_type === SchedulingEventType::Competition, 403);
        $teamAssignments = $assignment->shift->assignments()->where('status', 'published')->with(['crewProfile', 'timeEntry'])->get();
        abort_if($teamAssignments->every(fn (SchedulingShiftAssignment $teamAssignment): bool => $teamAssignment->timeEntry?->actual_finish_at !== null), 422, 'All crew are already clocked out.');
        $count = $finishTeam->execute($teamAssignments, $request->user(), $request->string('actual_finish_at')->toString(), $request->string('optional_note')->toString() ?: null);

        return back()->with('status', "Finish time saved for {$count} crew member".($count === 1 ? '.' : 's.'));
    }

    private function authoriseAssignment(Request $request, SchedulingShiftAssignment $assignment): void
    {
        abort_unless($assignment->crew_profile_id === $request->user()?->crewProfile?->id && $assignment->status === 'published', 403);
    }
}
