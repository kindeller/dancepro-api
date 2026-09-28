<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Concerts\Services\ConcertDirectorReviews;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Requests\SaveDirectorCorrectionsRequest;
use App\Http\Controllers\Controller;

class DirectorCorrectionsController extends Controller
{
    public function update(SaveDirectorCorrectionsRequest $request, SchedulingEvent $schedulingEvent, ConcertDirectorReviews $reviews)
    {
        $ready = $reviews->checks($schedulingEvent, $request->user(), $request->validated());

        return to_route('admin.concert-tracker.tasks.show', [$schedulingEvent, 'director_corrections', 'email' => $ready ? 1 : null])
            ->with('status', $ready ? 'Corrections saved. Review and send the email below.' : 'Corrections progress saved.');
    }
}
