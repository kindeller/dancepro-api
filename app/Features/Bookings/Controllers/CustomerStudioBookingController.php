<?php

namespace App\Features\Bookings\Controllers;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Services\StudioBookingPlans;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerStudioBookingController extends Controller
{
    public function index(Request $request, Studio $studio, StudioBookingPlans $plans): Response
    {
        return response()->view('customers.studios.bookings', [
            'studio' => $studio,
            'bookings' => $plans->forStudio($request->user(), $studio),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Studio $studio, ConcertBooking $concertBooking, StudioBookingPlans $plans): Response
    {
        return response()->view('public.concert-bookings.thanks', [
            'studio' => $studio,
            'receipt' => $plans->timeline($request->user(), $studio, $concertBooking),
            'bookingUuid' => $concertBooking->uuid,
            'booking' => $concertBooking,
            'parentUrl' => route('customer.studio-bookings.index', $studio),
            'submissionUrl' => route('customer.studio-bookings.submission', [$studio, $concertBooking]),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function submission(Request $request, Studio $studio, ConcertBooking $concertBooking, StudioBookingPlans $plans): Response
    {
        return response()->view('public.concert-bookings.submission', [
            'studio' => $studio,
            'receipt' => $plans->receipt($request->user(), $studio, $concertBooking),
            'parentUrl' => route('customer.studio-bookings.show', [$studio, $concertBooking]),
        ])->header('Cache-Control', 'private, no-store');
    }
}
