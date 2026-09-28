<?php

namespace App\Features\Bookings\Controllers;

use App\Features\Bookings\Actions\ConfirmBookingFamilies;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Requests\ConfirmBookingFamiliesRequest;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class BookingFamilyNumbersController extends Controller
{
    public function customer(ConfirmBookingFamiliesRequest $request, Studio $studio, ConcertBooking $concertBooking, ConfirmBookingFamilies $confirm): RedirectResponse
    {
        $confirm->execute($concertBooking, (int) $request->validated('final_family_count'), $request->user());

        return redirect()->route('customer.studio-bookings.show', [$studio, $concertBooking])->with('status', 'Final family numbers confirmed.'.($concertBooking->wants_concert_videography ? ' Your video quote has been updated.' : ''));
    }

    public function admin(ConfirmBookingFamiliesRequest $request, ConcertBooking $concertBooking, ConfirmBookingFamilies $confirm): RedirectResponse
    {
        $confirm->execute($concertBooking, (int) $request->validated('final_family_count'), $request->user());

        return redirect()->to(route('admin.concert-bookings.show', [$concertBooking, 'tab' => 'seats-families']))->with('status', 'Final family numbers recorded.'.($concertBooking->wants_concert_videography ? ' Video quote updated.' : ''));
    }
}
