<?php

namespace App\Features\Concerts\Controllers;

use App\Features\Concerts\Requests\DirectorReviewRequest;
use App\Features\Concerts\Services\ConcertDirectorReviews;
use App\Features\Customers\Services\StudioHubLoginCodes;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;

class DirectorReviewController extends Controller
{
    public function show(DirectorReviewRequest $request, SchedulingEvent $schedulingEvent, ConcertDirectorReviews $reviews)
    {
        $data = $reviews->page($schedulingEvent);

        return response()->view('customers.studios.concert-review', $data + ['event' => $schedulingEvent,
            'loginCode' => $data['released'] ? app(StudioHubLoginCodes::class)->video($schedulingEvent->concertBookingItem->concert, $schedulingEvent->studio) : null])
            ->header('Cache-Control', 'private, no-store');
    }

    public function feedback(DirectorReviewRequest $request, SchedulingEvent $schedulingEvent, ConcertDirectorReviews $reviews)
    {
        $reviews->feedback($schedulingEvent, $request->user(), $request->validated());

        return back()->with('status', 'Your corrections have been sent to DancePro. We will email you when the revised concert is ready.');
    }

    public function release(DirectorReviewRequest $request, SchedulingEvent $schedulingEvent, ConcertDirectorReviews $reviews)
    {
        $reviews->release($schedulingEvent, $request->user(), $request->validated());

        return back()->with('status', 'Your concert video is released. Share the access code with your dancers and families.');
    }

    public function video(DirectorReviewRequest $request, SchedulingEvent $schedulingEvent, string $file, ConcertDirectorReviews $reviews)
    {
        return $reviews->video($schedulingEvent, $file);
    }
}
