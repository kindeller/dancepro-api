<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Tracking\Requests\ConcertTrackerRequest;
use App\Features\Tracking\Services\ConcertTracker;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class AdminConcertTrackerController extends Controller
{
    public function __invoke(ConcertTrackerRequest $request, ConcertTracker $tracker): Response
    {
        $input = $request->validated();
        $view = $input['view'] ?? 'date';
        $year = (int) ($input['year'] ?? now()->year);
        $phase = $input['phase'] ?? 'all';
        $attention = $request->boolean('attention');

        return response()->view('admin.tracking.concert-tracker', $tracker->page($view, $year, $phase, $attention))
            ->header('Cache-Control', 'private, no-store');
    }
}
