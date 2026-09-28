<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Models\ConcertTitleCardTemplate;
use App\Features\Programmes\Requests\SaveConcertTitleCardTemplateRequest;
use App\Features\Programmes\Services\ConcertTitleCards;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertTracker;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminConcertTitleCardController extends Controller
{
    public function show(Request $request, SchedulingEvent $schedulingEvent, ConcertTitleCards $titles, BookingTrackerBar $bar, ConcertTracker $tracker): View|RedirectResponse
    {
        abort_unless($schedulingEvent->event_type === SchedulingEventType::Concert, 404);
        if ($schedulingEvent->concertBookingItem?->booking) {
            return to_route('admin.concert-bookings.show', [$schedulingEvent->concertBookingItem->booking, 'tab' => 'video-titles', 'event' => $schedulingEvent->uuid]);
        }

        return view('admin.programmes.title-cards', $titles->page($schedulingEvent) + [
            'trackerBar' => $bar->videoTitlesPage($tracker->detail($schedulingEvent, 'video_titles'), $request->session()->get('concert_tracker_context', [])),
        ]);
    }

    public function update(SaveConcertTitleCardTemplateRequest $request, SchedulingEvent $schedulingEvent): RedirectResponse
    {
        abort_unless($schedulingEvent->event_type === SchedulingEventType::Concert, 404);
        ConcertTitleCardTemplate::updateOrCreate(
            ['scheduling_event_id' => $schedulingEvent->id],
            $request->validated() + ['font_family' => 'Atlanta', 'updated_by_user_id' => $request->user()->id],
        );

        return back()->with('status', 'Title card template saved.');
    }
}
