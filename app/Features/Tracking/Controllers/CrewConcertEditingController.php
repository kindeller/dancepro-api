<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Actions\SaveConcertTrackerTaskFields;
use App\Features\Tracking\Requests\ConcertEditingRequest;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertEditing;
use App\Features\Tracking\Services\ConcertEditingDownloads;
use App\Features\Tracking\Services\ConcertTracker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CrewConcertEditingController extends Controller
{
    public function index(Request $request, ConcertEditing $editing): Response
    {
        return response()->view('crew.editing.index', ['events' => $editing->assignedTo($request->user())])
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(ConcertEditingRequest $request, SchedulingEvent $schedulingEvent, ConcertTracker $tracker, BookingTrackerBar $bar): Response
    {
        $detail = $tracker->detail($schedulingEvent, 'concert_edited');
        $trackerBar = array_replace($bar->editingPage($detail), [
            'eventUrl' => null, 'lockedPanels' => ['due', 'assignment'], 'assignees' => collect(),
            'statuses' => array_diff_key(BookingTrackerBar::STATUSES, ['not_required' => true]),
            'returnUrl' => route('crew.editing.index'), 'returnLabel' => '← My Tasks',
            'updateUrl' => route('crew.editing.update', $schedulingEvent),
        ]);

        return response()->view('admin.tracking.concert-editing', $detail + [
            'trackerBar' => $trackerBar, 'editingLayout' => 'layouts.crew',
            'editingDownloads' => app(ConcertEditingDownloads::class)->page($schedulingEvent, $request->user()),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(ConcertEditingRequest $request, SchedulingEvent $schedulingEvent, ConcertTracker $tracker, SaveConcertTrackerTaskFields $save): JsonResponse|RedirectResponse
    {
        $detail = $tracker->detail($schedulingEvent, 'concert_edited');
        $fields = $request->safe()->except('panel');
        if ($request->validated('panel') === 'status') {
            if ($detail['status']['sourceDriven']) {
                throw ValidationException::withMessages(['status_override' => 'Editing is not required for this event.']);
            }
            $fields['status'] = $fields['status_override'];
        }
        $save->execute('event', $schedulingEvent->id, 'concert_edited', $request->user(), $fields);
        $url = route('crew.editing.show', $schedulingEvent);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Editing task updated.', 'data' => ['redirect' => $url]]);
        }

        return redirect()->to($url)->with('status', 'Editing task updated.');
    }
}
