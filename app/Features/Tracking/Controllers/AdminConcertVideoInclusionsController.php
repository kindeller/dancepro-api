<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Requests\SaveConcertVideoInclusionsRequest;
use App\Features\Tracking\Services\ConcertVideoReadiness;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class AdminConcertVideoInclusionsController extends Controller
{
    public function __invoke(SaveConcertVideoInclusionsRequest $request, SchedulingEvent $schedulingEvent, ConcertVideoReadiness $readiness): RedirectResponse
    {
        $readiness->saveInclusions($schedulingEvent, $request->user(), $request->validated('batch_uuid'), $request->validated('inclusions', []));

        return back()->with('status', 'Studio video inclusions saved.');
    }
}
