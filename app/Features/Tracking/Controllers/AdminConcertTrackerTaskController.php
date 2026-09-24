<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Actions\UpdateConcertTrackerTask;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Requests\ConcertTrackerRequest;
use App\Features\Tracking\Requests\SendConcertTrackerEmailRequest;
use App\Features\Tracking\Requests\UpdateConcertTrackerEmailTemplateRequest;
use App\Features\Tracking\Requests\UpdateConcertTrackerTaskRequest;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Tracking\Services\ConcertTrackerEmails;
use App\Features\Tracking\Services\EmailBranding;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class AdminConcertTrackerTaskController extends Controller
{
    public function show(ConcertTrackerRequest $request, SchedulingEvent $schedulingEvent, string $taskKey, ConcertTracker $tracker, EmailBranding $branding): Response
    {
        return response()->view('admin.tracking.concert-task', $tracker->detail($schedulingEvent, $taskKey) + ['emailBranding' => $branding->for($request->user()->id)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(UpdateConcertTrackerTaskRequest $request, SchedulingEvent $schedulingEvent, string $taskKey, UpdateConcertTrackerTask $tasks): RedirectResponse
    {
        $tasks->execute($schedulingEvent, $taskKey, $request->user(), $request->validated());

        return back()->with('status', 'Tracker task updated.');
    }

    public function updateTemplate(UpdateConcertTrackerEmailTemplateRequest $request, SchedulingEvent $schedulingEvent, string $taskKey, ConcertTracker $tracker): RedirectResponse
    {
        $data = $tracker->detail($schedulingEvent, $taskKey);
        abort_unless($data['template'], 404);
        $input = $request->validated();
        $input['is_active'] = $request->boolean('is_active');
        $data['template']->update($input);

        return back()->with('status', 'Email template updated.');
    }

    public function send(SendConcertTrackerEmailRequest $request, SchedulingEvent $schedulingEvent, string $taskKey, ConcertTracker $tracker, ConcertTrackerEmails $emails): RedirectResponse
    {
        $data = $tracker->detail($schedulingEvent, $taskKey);
        abort_unless($data['template'] instanceof ConcertTrackerEmailTemplate, 404);
        $delivery = $emails->queue($schedulingEvent, $data['task'], $data['template'], $request->user(), $request->validated());

        return back()->with('status', $delivery->status === 'scheduled'
            ? 'Email scheduled. The task will complete when delivery succeeds.'
            : 'Email queued. The task will complete when delivery succeeds.');
    }
}
