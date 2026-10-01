<?php

namespace App\Features\Bookings\Controllers;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Requests\SaveStudioBookingDraftRequest;
use App\Features\Bookings\Services\StudioBookingDrafts;
use App\Features\Bookings\Services\StudioBookingPlans;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerStudioBookingController extends Controller
{
    public function saveDraft(SaveStudioBookingDraftRequest $request, Studio $studio, StudioBookingDrafts $drafts): RedirectResponse
    {
        $drafts->save($request->user(), $studio, $request->validated());

        return to_route('concert-bookings.create', ['studio' => $studio->uuid])->with('status', 'Draft saved. You can return using Continue booking in Studio Hub. Your booking has not been submitted.');
    }

    public function index(Request $request, Studio $studio, StudioBookingPlans $plans): Response
    {
        return response()->view('customers.studios.bookings', [
            'studio' => $studio,
            'bookings' => $plans->forStudio($request->user(), $studio),
            'bookingDraft' => app(StudioBookingDrafts::class)->find($request->user(), $studio),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function previous(Request $request, Studio $studio, StudioBookingPlans $plans): Response
    {
        return response()->view('customers.studios.bookings', [
            'studio' => $studio,
            'bookings' => $plans->previousForStudio($request->user(), $studio),
            'previousForms' => true,
            'bookingDraft' => app(StudioBookingDrafts::class)->find($request->user(), $studio),
            'latestBooking' => $plans->latestForStudio($request->user(), $studio),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Studio $studio, ConcertBooking $concertBooking, StudioBookingPlans $plans): Response
    {
        return response()->view('public.concert-bookings.thanks', [
            'studio' => $studio,
            'receipt' => $plans->timeline($request->user(), $studio, $concertBooking),
            'bookingUuid' => $concertBooking->uuid,
            'booking' => $concertBooking,
            'parentUrl' => route('customer.studio-communications.index', [$studio, 'tab' => 'events']),
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
