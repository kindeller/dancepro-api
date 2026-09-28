<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Bookings\Actions\ApproveBookingForConfirmation;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Tracking\Actions\SaveConcertTrackerTaskFields;
use App\Features\Tracking\Requests\UpdateBookingTrackerBarRequest;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AdminBookingTrackerBarController extends Controller
{
    public function __invoke(UpdateBookingTrackerBarRequest $request, ConcertBooking $concertBooking, SaveConcertTrackerTaskFields $save, BookingTrackerBar $bar, ApproveBookingForConfirmation $approve): RedirectResponse|JsonResponse
    {
        if ($request->validated('panel') === 'status' && $request->validated('status_override') === 'approved') {
            $url = $approve->execute($concertBooking, $request->user(), $request->safe()->only('studio_uuid'));

            return $request->expectsJson()
                ? response()->json(['success' => true, 'message' => 'Booking approved.', 'data' => ['redirect' => $url]])
                : redirect()->to($url)->with('status', 'Booking approved. Review the confirmation template and send when ready.');
        }

        $save->execute('booking', $concertBooking->id, 'booking_received', $request->user(), $request->safe()->except(['panel', 'studio_uuid']));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tracker updated.',
                'data' => ['html' => view('admin.tracking._task-bar', ['bar' => $bar->page($concertBooking, $request->session()->get('concert_tracker_context', [])) + ['hideBackLink' => true]])->render()],
            ]);
        }

        return redirect()->route('admin.concert-bookings.show', $concertBooking)->with('status', 'Tracker updated.');
    }
}
