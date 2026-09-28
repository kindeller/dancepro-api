<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Actions\SaveConcertTrackerTaskFields;
use App\Features\Tracking\Requests\UpdateBookingTrackerBarRequest;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AdminSeatsFamiliesEmailTrackerController extends Controller
{
    public function __invoke(UpdateBookingTrackerBarRequest $request, SchedulingEvent $schedulingEvent, SaveConcertTrackerTaskFields $save, ConcertTracker $tracker, BookingTrackerBar $bar, string $taskKey = 'final_numbers_email'): JsonResponse|RedirectResponse
    {
        abort_unless($schedulingEvent->event_type->value === 'concert', 404);
        abort_unless(in_array($taskKey, ConcertTrackerTaskCatalog::GUARDED_EMAIL_TASKS, true), 404);
        $detail = $tracker->detail($schedulingEvent, $taskKey);
        abort_if(in_array($taskKey, ['director_corrections', 'invoice_sent'], true) && $request->validated('panel') === 'status', 422, 'Corrections complete when the follow-up email is sent.');
        $fields = $request->safe()->except(['panel', 'studio_uuid']);
        if ($request->validated('panel') === 'status') {
            $approved = in_array($fields['status_override'], ['approved', 'complete'], true);
            $fields['status'] = $approved ? 'complete' : ($fields['status_override'] ?? 'not_started');
            $fields['status_override'] = $approved ? 'complete' : null;
        }
        $save->execute($detail['task']->scope_type, $detail['task']->scope_id, $taskKey, $request->user(), $fields);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Tracker updated.', 'data' => [
                'html' => view('admin.tracking._task-bar', ['bar' => $bar->emailPage($tracker->detail($schedulingEvent, $taskKey), $request->session()->get('concert_tracker_context', []))])->render(),
            ]]);
        }

        return redirect()->route('admin.concert-tracker.tasks.show', [$schedulingEvent, $taskKey])->with('status', 'Tracker updated.');
    }
}
