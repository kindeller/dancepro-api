<?php

namespace App\Features\Studios\Controllers;

use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Actions\SaveConcertEventSeats;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Requests\SaveConcertEventSeatsRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class StudioEventSeatsController extends Controller
{
    public function store(SaveConcertEventSeatsRequest $request, Studio $studio, SchedulingEvent $schedulingEvent, StudioCommunicationHistory $history, SaveConcertEventSeats $save): RedirectResponse|JsonResponse
    {
        abort_unless($history->canView($request->user(), $studio) && $schedulingEvent->studio_id === $studio->id, 403);
        $save->execute($schedulingEvent, $request->user(), $request->validated('roles'));

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Reserved seating saved.', 'data' => []]);
        }

        return back()->with('status', 'Reserved seating saved for '.$schedulingEvent->name.'.');
    }
}
