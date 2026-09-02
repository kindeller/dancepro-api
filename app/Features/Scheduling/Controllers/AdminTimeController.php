<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Scheduling\Actions\SaveAssignmentTime;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Requests\SaveAssignmentTimeRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AdminTimeController extends Controller
{
    public function update(SaveAssignmentTimeRequest $request, SchedulingShiftAssignment $assignment, SaveAssignmentTime $saveTime): RedirectResponse
    {
        Gate::authorize('manageScheduling');
        $saveTime->execute($assignment, $request->user(), $request->input('actual_clock_in_at'), $request->input('actual_finish_at'), $request->input('optional_note'), 'admin');

        return back()->with('status', 'Crew time record updated.');
    }
}
