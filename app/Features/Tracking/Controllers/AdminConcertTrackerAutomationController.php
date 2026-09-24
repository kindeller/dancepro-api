<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Actions\SendEmailBrandingTest;
use App\Features\Tracking\Requests\ConcertTrackerRequest;
use App\Features\Tracking\Requests\UpdateConcertTrackerSettingsRequest;
use App\Features\Tracking\Services\ConcertTrackerAutomation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminConcertTrackerAutomationController extends Controller
{
    public function index(ConcertTrackerRequest $request, ConcertTrackerAutomation $automation): Response
    {
        $year = (int) ($request->validated('year') ?? now()->year);

        return response()->view('admin.tracking.concert-automation', $automation->page($year))
            ->header('Cache-Control', 'private, no-store');
    }

    public function schedule(Request $request, SchedulingEvent $schedulingEvent, string $taskKey, ConcertTrackerAutomation $automation): RedirectResponse
    {
        $automation->schedule($schedulingEvent, $taskKey, $request->user());

        return back()->with('status', 'Email added to the schedule.');
    }

    public function settings(Request $request, ConcertTrackerAutomation $automation): Response
    {
        return response()->view('admin.tracking.concert-automation-settings', $automation->settingsPage($request->user()))
            ->header('Cache-Control', 'private, no-store');
    }

    public function updateSettings(UpdateConcertTrackerSettingsRequest $request, ConcertTrackerAutomation $automation): RedirectResponse
    {
        $automation->updateSettings($request->validated(), $request->user());

        return back()->with('status', 'Tracker automation settings updated.');
    }

    public function sendBrandingTest(Request $request, SendEmailBrandingTest $sendTest): RedirectResponse
    {
        $sendTest->execute($request->user());

        return back()->with('status', 'Test email sent to '.$request->user()->email.'.');
    }
}
