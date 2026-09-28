<?php

namespace App\Features\Bookings\Controllers;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Requests\SaveBookingEventSeatsRequest;
use App\Features\Bookings\Requests\UploadBookingEventFilesRequest;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Actions\SaveConcertEventSeats;
use App\Features\Studios\Actions\StoreStudioEventFiles;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AdminBookingEventMaterialsController extends Controller
{
    public function seats(SaveBookingEventSeatsRequest $request, ConcertBookingItem $concertBookingItem, SaveConcertEventSeats $save): RedirectResponse
    {
        $save->execute($this->event($concertBookingItem), $request->user(), $request->validated('roles'));

        return redirect()->to(route('admin.concert-bookings.show', [$concertBookingItem->booking, 'tab' => 'seats-families']))->with('status', 'Reserved seats updated. The Studio Hub and tracker use these same details.');
    }

    public function files(UploadBookingEventFilesRequest $request, ConcertBookingItem $concertBookingItem, StoreStudioEventFiles $store): RedirectResponse
    {
        $store->execute($this->event($concertBookingItem), $request->user(), $request->validated('kind'), $request->file('files'), $request->validated('replace_uuid'));

        return back()->with('status', 'Event files uploaded. Review the files before marking them approved.');
    }

    private function event(ConcertBookingItem $item): SchedulingEvent
    {
        if ($item->cancelled_at || $item->approval_status !== 'approved' || ! $item->schedulingEvent) {
            throw ValidationException::withMessages(['event' => 'Approve this event before recording seats or studio files.']);
        }

        return $item->schedulingEvent;
    }
}
