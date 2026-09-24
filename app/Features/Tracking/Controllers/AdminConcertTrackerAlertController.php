<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Tracking\Models\ConcertTrackerAlert;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminConcertTrackerAlertController extends Controller
{
    public function index(Request $request): Response
    {
        $alerts = ConcertTrackerAlert::query()->where('user_id', $request->user()->id)->whereNull('resolved_at')
            ->with('event')->latest()->get();

        return response()->view('admin.tracking.concert-alerts', compact('alerts'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request, ConcertTrackerAlert $alert): RedirectResponse
    {
        abort_unless($alert->user_id === $request->user()->id, 404);
        $alert->update(['read_at' => now()]);

        return redirect()->to($alert->url);
    }

    public function readAll(Request $request): RedirectResponse
    {
        ConcertTrackerAlert::query()->where('user_id', $request->user()->id)->whereNull('resolved_at')->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'Tracker alerts marked as read.');
    }
}
