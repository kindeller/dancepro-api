<?php

namespace App\Features\Bookings\Controllers;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Services\BookingConfirmationPage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AdminBookingConfirmationController extends Controller
{
    public function __invoke(Request $request, ConcertBooking $concertBooking, BookingConfirmationPage $page): Response
    {
        Gate::authorize('manageScheduling');

        return response()->view('admin.concert-bookings.confirmation', $page->data($concertBooking, $request->user(), $request->session()->get('concert_tracker_context', [])))
            ->header('Cache-Control', 'private, no-store');
    }
}
