<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Actions\AcceptShiftCoverRequest;
use App\Features\Scheduling\Actions\CreateShiftCoverRequest;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Models\ShiftCoverRequest;
use App\Features\Scheduling\Requests\StoreShiftCoverRequest;
use App\Features\Scheduling\Services\EligibleCoverCandidates;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrewCoverRequestController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $this->profile($request);

        return redirect()->route('crew.availability.index', ['view' => 'cover']);
    }

    public function create(Request $request, SchedulingShiftAssignment $assignment, EligibleCoverCandidates $eligibleCandidates): View
    {
        $profile = $this->profile($request);
        abort_unless($assignment->crew_profile_id === $profile->id && $assignment->status === 'published', 403);
        $assignment->load(['role', 'timeEntry', 'shift.schedulingEvent.venue']);
        abort_if($assignment->timeEntry?->actual_clock_in_at || $assignment->shift->shift_date->lt(today()), 404);

        return view('crew.cover.create', ['assignment' => $assignment, 'candidates' => $eligibleCandidates->execute($assignment)]);
    }

    public function store(StoreShiftCoverRequest $request, SchedulingShiftAssignment $assignment, CreateShiftCoverRequest $createCover): RedirectResponse
    {
        $createCover->execute($assignment, $request->user()->crewProfile, $request->validated('recipients'), $request->validated('message'));

        return redirect()->route('crew.availability.index', ['view' => 'cover'])->with('status', 'Cover request sent. The first eligible person to accept will receive the shift.');
    }

    public function accept(Request $request, ShiftCoverRequest $coverRequest, AcceptShiftCoverRequest $acceptCover): RedirectResponse
    {
        $assignment = $acceptCover->execute($coverRequest, $this->profile($request));

        return redirect()->route('crew.assignments.show', $assignment)->with('status', 'Cover accepted. Please review and acknowledge your new shift.');
    }

    private function profile(Request $request): CrewProfile
    {
        abort_unless($request->user()?->is_active && $request->user()?->crewProfile !== null, 403);

        return $request->user()->crewProfile;
    }
}
