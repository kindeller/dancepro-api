<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Tracking\Actions\SaveConcertTrackerTaskFields;
use App\Features\Tracking\Requests\UpdateTaskTrackerBarRequest;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AdminBookingNumbersTrackerController extends Controller
{
    public function __invoke(UpdateTaskTrackerBarRequest $request, ConcertBooking $concertBooking, SaveConcertTrackerTaskFields $save, BookingTrackerBar $bar): JsonResponse|RedirectResponse
    {
        if ($request->validated('panel') === 'status') {
            throw ValidationException::withMessages(['status_override' => 'Confirm the family count and all concert seats to complete this task.']);
        }
        $save->execute('booking', $concertBooking->id, 'final_numbers_received', $request->user(), $request->safe()->except(['panel', 'studio_uuid']));
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Tracker updated.', 'data' => [
                'html' => view('admin.tracking._task-bar', ['bar' => $bar->numbersPage($concertBooking, $request->session()->get('concert_tracker_context', [])) + ['hideBackLink' => true]])->render(),
            ]]);
        }

        return redirect()->route('admin.concert-bookings.show', [$concertBooking, 'tab' => 'seats-families'])->with('status', 'Tracker updated.');
    }
}
