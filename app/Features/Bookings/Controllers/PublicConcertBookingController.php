<?php

namespace App\Features\Bookings\Controllers;

use App\Features\Bookings\Actions\CreateConcertBooking;
use App\Features\Bookings\Requests\StoreConcertBookingRequest;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Venues\Models\Venue;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicConcertBookingController extends Controller
{
    public function create(Request $request): View
    {
        return view('public.concert-bookings.create', [
            'venues' => Venue::query()->orderBy('name')->get(),
            'eventTypes' => EventTypeDefinition::query()->where('is_active', true)->where('system_category', 'concert')->orderBy('name')->get(),
            'selectedEventTypeUuid' => $request->string('event_type')->toString(),
        ]);
    }

    public function store(StoreConcertBookingRequest $request, CreateConcertBooking $createBooking): RedirectResponse
    {
        $createBooking->execute($request->validated());

        return redirect()->route('concert-bookings.thanks');
    }

    public function thanks(): View
    {
        return view('public.concert-bookings.thanks');
    }
}
