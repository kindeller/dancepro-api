<?php

namespace App\Features\Studios\Controllers;

use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Actions\SaveStudioEventRequirements;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Requests\SaveStudioEventRequirementsRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class StudioEventRequirementsController extends Controller
{
    public function store(SaveStudioEventRequirementsRequest $request, Studio $studio, SchedulingEvent $schedulingEvent, StudioCommunicationHistory $history, SaveStudioEventRequirements $save): RedirectResponse
    {
        abort_unless($history->canView($request->user(), $studio) && $schedulingEvent->studio_id === $studio->id, 403);
        $save->execute($schedulingEvent, $request->user(), $request->validated('requirements', []), $request->validated('group', 'portrait'));

        return back()->with('status', ($request->validated('group') === 'event_details' ? 'Event details confirmation' : 'Portrait shoot requirements').' updated for '.$schedulingEvent->name.'.');
    }
}
