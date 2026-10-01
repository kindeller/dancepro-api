<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Actions\SaveConcertTrackerTaskFields;
use App\Features\Tracking\Requests\UpdateTaskTrackerBarRequest;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertTracker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AdminSourceTrackerBarController extends Controller
{
    public function __invoke(UpdateTaskTrackerBarRequest $request, SchedulingEvent $schedulingEvent, string $taskKey, SaveConcertTrackerTaskFields $save, ConcertTracker $tracker, BookingTrackerBar $bar): JsonResponse|RedirectResponse
    {
        abort_unless(in_array($taskKey, ['crew_ready', 'programme_prepared', 'slideshow_received', 'video_titles', 'photos_uploaded', 'concert_edited', 'quality_check', 'video_uploaded', 'director_corrections', 'photo_release', 'final_release', 'invoice_sent'], true), 404);
        $detail = $tracker->detail($schedulingEvent, $taskKey);
        $fields = $request->safe()->except(['panel', 'studio_uuid']);
        if ($request->validated('panel') === 'status' && (! in_array($taskKey, ['slideshow_received', 'video_titles', 'concert_edited'], true) || $detail['status']['sourceDriven'])) {
            throw ValidationException::withMessages(['status_override' => 'Complete this task through its source page.']);
        }
        if ($request->validated('panel') === 'status') {
            $fields['status'] = $fields['status_override'] === 'approved' ? 'complete' : ($fields['status_override'] ?? 'not_started');
            $fields['status_override'] = $fields['status'];
        }
        $save->execute($detail['task']->scope_type, $detail['task']->scope_id, $taskKey, $request->user(), $fields);
        if ($request->expectsJson()) {
            if ($taskKey === 'concert_edited') {
                return response()->json(['success' => true, 'message' => 'Editing task updated.', 'data' => [
                    'redirect' => route('admin.concert-tracker.tasks.show', [$schedulingEvent, $taskKey]),
                ]]);
            }

            return response()->json(['success' => true, 'message' => 'Tracker updated.', 'data' => [
                'html' => view('admin.tracking._task-bar', ['bar' => $bar->{match ($taskKey) {
                    'invoice_sent' => 'invoicePage', 'director_corrections', 'photo_release', 'final_release' => 'releasePage', 'video_uploaded' => 'videoUploadsPage', 'quality_check' => 'qualityPage', 'slideshow_received' => 'studioMediaPage', 'video_titles' => 'videoTitlesPage', default => 'sourcePage'
                }}($tracker->detail($schedulingEvent, $taskKey), $request->session()->get('concert_tracker_context', []))])->render(),
            ]]);
        }

        return back()->with('status', 'Tracker updated.');
    }
}
