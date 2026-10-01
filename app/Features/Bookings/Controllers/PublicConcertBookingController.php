<?php

namespace App\Features\Bookings\Controllers;

use App\Features\Bookings\Actions\CreateConcertBooking;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Requests\CreateConcertBookingRequest;
use App\Features\Bookings\Requests\StoreConcertBookingRequest;
use App\Features\Bookings\Services\BookingSubmissionAcknowledgements;
use App\Features\Bookings\Services\ConcertBookingPrefill;
use App\Features\Bookings\Services\ConcertBookingSummary;
use App\Features\Bookings\Services\StudioBookingDrafts;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Venues\Models\Venue;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicConcertBookingController extends Controller
{
    public function create(CreateConcertBookingRequest $request, ConcertBookingPrefill $prefill): Response
    {
        return response()->view('public.concert-bookings.create', [
            'venues' => Venue::query()->orderBy('name')->get(),
            'eventTypes' => EventTypeDefinition::query()->where('is_active', true)->where('system_category', 'concert')->orderBy('name')->get(),
            'selectedEventTypeUuid' => $request->string('event_type')->toString(),
        ] + $prefill->forUser($request->user(), $request->validated('studio')))->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreConcertBookingRequest $request, CreateConcertBooking $createBooking, BookingSubmissionAcknowledgements $acknowledgements, StudioBookingDrafts $drafts): RedirectResponse
    {
        $booking = $createBooking->execute($request->validated(), $request->bookingStudio());
        $request->session()->put('concert_booking_receipts.'.$booking->uuid, true);
        $request->session()->put('latest_concert_booking', $booking->uuid);
        if ($request->bookingStudio()) {
            $drafts->submitted($request->bookingStudio(), $request->validated());
        }
        $acknowledgements->send($booking, $request->user());

        return $request->bookingStudio()
            ? redirect()->route('customer.studio-bookings.show', [$request->bookingStudio(), $booking])
            : redirect()->route('concert-bookings.thanks');
    }

    public function thanks(Request $request, ConcertBookingSummary $summary): Response
    {
        $uuid = $request->session()->get('latest_concert_booking');

        return response()->view('public.concert-bookings.thanks', [
            'receipt' => $summary->submitted($uuid),
            'bookingUuid' => $uuid,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function submission(Request $request, ConcertBooking $concertBooking): Response
    {
        abort_unless($request->session()->get('concert_booking_receipts.'.$concertBooking->uuid), 403);
        abort_unless($concertBooking->submission_snapshot, 404);

        return response()->view('public.concert-bookings.submission', [
            'receipt' => $concertBooking->submission_snapshot,
        ])->header('Cache-Control', 'private, no-store');
    }
}
