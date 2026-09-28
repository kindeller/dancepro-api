<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Requests\SaveConcertQualityChecklistRequest;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertQualityChecklist;
use App\Features\Tracking\Services\ConcertTracker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AdminConcertQualityChecklistController extends Controller
{
    public function __invoke(SaveConcertQualityChecklistRequest $request, SchedulingEvent $schedulingEvent, ConcertTracker $tracker, ConcertQualityChecklist $checklist): RedirectResponse|JsonResponse
    {
        abort_unless($schedulingEvent->event_type->value === 'concert' && ! $schedulingEvent->cancelled_at, 404);
        $detail = $tracker->detail($schedulingEvent, 'quality_check');
        abort_if($detail['status']['key'] === 'not_required', 422, 'Quality checking is not required for this event.');
        $checklist->save($detail['task'], $request->user(), $request->boolean('complete_all') ? $checklist->keys() : $request->validated('checks', []), $request->validated('fingerprint'));

        if ($request->expectsJson()) {
            $detail = $tracker->detail($schedulingEvent->fresh(), 'quality_check');

            return response()->json(['success' => true, 'message' => 'Quality checklist saved.', 'data' => [
                'fingerprint' => $checklist->fingerprint($detail['task']),
                'checked' => $checklist->summary($detail['task'])['checked'],
                'complete' => $checklist->summary($detail['task'])['complete'],
                'completion' => $detail['task']->completed_at ? 'Completed by '.($detail['task']->completedBy?->name ?? 'Former staff member').' · '.$detail['task']->completed_at->format('j M Y, g:i a') : '',
                'checks' => $checklist->page($detail['task'])['checks'],
                'reviewers' => $checklist->page($detail['task'])['reviewers'],
                'html' => view('admin.tracking._task-bar', ['bar' => app(BookingTrackerBar::class)->qualityPage($detail, $request->session()->get('concert_tracker_context', []))])->render(),
            ]])->header('Cache-Control', 'private, no-store');
        }

        return to_route('admin.concert-tracker.tasks.show', [$schedulingEvent, 'quality_check'])->with('status', 'Quality checklist saved.');
    }
}
