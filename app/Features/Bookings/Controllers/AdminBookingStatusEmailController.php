<?php

namespace App\Features\Bookings\Controllers;

use App\Features\Bookings\Models\BookingStatusNotification;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Requests\SendBookingStatusEmailRequest;
use App\Features\Bookings\Services\BookingStatusEmails;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminBookingStatusEmailController extends Controller
{
    public function show(Request $request, ConcertBooking $concertBooking, BookingStatusNotification $bookingStatusNotification, BookingStatusEmails $emails): View
    {
        Gate::authorize('manageScheduling');

        return view('admin.concert-bookings.status-email', $emails->page($concertBooking, $bookingStatusNotification, $request->session()->get('concert_tracker_context', [])));
    }

    public function refresh(Request $request, ConcertBooking $concertBooking, BookingStatusNotification $bookingStatusNotification, BookingStatusEmails $emails): RedirectResponse
    {
        Gate::authorize('manageScheduling');
        $draft = $emails->refreshDraft($concertBooking, $bookingStatusNotification, $request->user());

        return redirect()->route('admin.concert-bookings.status-email', [$concertBooking, $draft]);
    }

    public function send(SendBookingStatusEmailRequest $request, ConcertBooking $concertBooking, BookingStatusNotification $bookingStatusNotification, BookingStatusEmails $emails): RedirectResponse
    {
        $emails->send($concertBooking, $bookingStatusNotification, $request->user(), $request->validated());

        return back()->with('status', $concertBooking->studio_id ? 'Booking notification sent and recorded in Studio messages.' : 'Booking notification sent.');
    }
}
