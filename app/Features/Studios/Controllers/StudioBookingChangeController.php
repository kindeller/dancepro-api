<?php

namespace App\Features\Studios\Controllers;

use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Actions\CreateStudioBookingChange;
use App\Features\Studios\Actions\ReviewStudioBookingChange;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioBookingChangeRequest as BookingChange;
use App\Features\Studios\Requests\ReviewStudioBookingChangeRequest;
use App\Features\Studios\Requests\StoreStudioBookingChangeRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class StudioBookingChangeController extends Controller
{
    public function store(StoreStudioBookingChangeRequest $request, Studio $studio, SchedulingEvent $schedulingEvent, StudioCommunicationHistory $history, CreateStudioBookingChange $create): RedirectResponse
    {
        abort_unless($history->canView($request->user(), $studio) && $schedulingEvent->studio_id === $studio->id, 403);
        $create->execute($schedulingEvent, $request->user(), $request->validated());

        return to_route('customer.studio-communications.index', [$studio, 'tab' => 'events'])
            ->with('status', 'Your booking change request has been sent to DancePro.');
    }

    public function review(ReviewStudioBookingChangeRequest $request, BookingChange $bookingChange, ReviewStudioBookingChange $review): RedirectResponse
    {
        $review->execute($bookingChange, $request->user(), $request->validated('status'));

        return back()->with('status', 'Booking change request updated.');
    }
}
