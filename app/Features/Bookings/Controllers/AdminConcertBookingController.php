<?php

namespace App\Features\Bookings\Controllers;

use App\Features\Bookings\Actions\ApproveBookingForConfirmation;
use App\Features\Bookings\Actions\BulkUpdateConcertBookingItems;
use App\Features\Bookings\Actions\CancelConcertBookingItem;
use App\Features\Bookings\Actions\ChangeBookingStatus;
use App\Features\Bookings\Actions\CreateStudioFromBooking;
use App\Features\Bookings\Actions\ReconcileBookingStudioContact;
use App\Features\Bookings\Actions\ResolveConcertBookingVenue;
use App\Features\Bookings\Actions\StoreConcertBookingProgramme;
use App\Features\Bookings\Actions\UpdateConcertBooking;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Requests\BulkUpdateConcertBookingItemsRequest;
use App\Features\Bookings\Requests\CancelBookingRequest;
use App\Features\Bookings\Requests\CancelConcertBookingItemRequest;
use App\Features\Bookings\Requests\CreateStudioFromBookingRequest;
use App\Features\Bookings\Requests\ReconcileBookingStudioContactRequest;
use App\Features\Bookings\Requests\ResolveConcertBookingVenueRequest;
use App\Features\Bookings\Requests\ReviewConcertBookingRequest;
use App\Features\Bookings\Requests\StoreConcertBookingProgrammeRequest;
use App\Features\Bookings\Requests\UpdateConcertBookingRequest;
use App\Features\Bookings\Services\BookingArtwork;
use App\Features\Bookings\Services\BookingChangeReview;
use App\Features\Bookings\Services\BookingInvoiceWorkspace;
use App\Features\Bookings\Services\BookingProgrammeWorkspace;
use App\Features\Bookings\Services\BookingSeatsAndFamilies;
use App\Features\Bookings\Services\BookingStudioMediaWorkspace;
use App\Features\Bookings\Services\BookingVideoTitlesWorkspace;
use App\Features\Bookings\Services\ReviewBookingStudioContact;
use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Studios\Actions\StoreStudioEventFiles;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Venues\Models\Venue;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminConcertBookingController extends Controller
{
    public function index(BookingArtwork $artwork): View
    {
        Gate::authorize('manageScheduling');

        $items = ConcertBookingItem::query()
            ->with(['booking', 'eventTypeDefinition', 'venue', 'schedulingEvent.studio', 'schedulingEvent.competitionContact'])
            ->whereHas('booking', fn ($query) => $query->where('status', '!=', 'cancelled'))
            ->where('approval_status', '!=', 'cancelled')
            ->orderBy('event_date')
            ->orderBy('starts_at')
            ->paginate(50);

        return view('admin.concert-bookings.index', [
            'items' => $items,
            'artworkUrls' => $artwork->forItems($items->getCollection()),
        ]);
    }

    public function show(Request $request, ConcertBooking $concertBooking, ReviewBookingStudioContact $reviewContact, BookingChangeReview $changes, BookingTrackerBar $trackerBar, BookingSeatsAndFamilies $seatsAndFamilies, BookingProgrammeWorkspace $programmeWorkspace, BookingStudioMediaWorkspace $studioMediaWorkspace, BookingVideoTitlesWorkspace $videoTitlesWorkspace, BookingInvoiceWorkspace $invoiceWorkspace): View
    {
        Gate::authorize('manageScheduling');
        $concertBooking->load(['items.eventTypeDefinition', 'items.schedulingEvent.shifts', 'items.schedulingEvent.roleRequirements.crewRole', 'items.schedulingEvent.roleSeats', 'items.schedulingEvent.studioFiles', 'items.venue']);

        $bookingTab = in_array($request->query('tab'), ['seats-families', 'programme', 'studio-media', 'video-titles', 'invoice'], true) ? $request->query('tab') : 'booking';
        $programmePage = $bookingTab === 'programme' ? $programmeWorkspace->page($concertBooking, $request->string('event')->toString() ?: null, $request->session()->get('concert_tracker_context', [])) : [];

        $mediaPage = $bookingTab === 'studio-media' ? $studioMediaWorkspace->page($concertBooking, $request->string('event')->toString() ?: null, $request->user(), $request->session()->get('concert_tracker_context', [])) : [];

        $titlesPage = $bookingTab === 'video-titles' ? $videoTitlesWorkspace->page($concertBooking, $request->string('event')->toString() ?: null, $request->session()->get('concert_tracker_context', [])) : [];

        $invoicePage = $bookingTab === 'invoice' ? $invoiceWorkspace->page($concertBooking, $request->string('invoice')->toString() ?: null, $request->session()->get('concert_tracker_context', [])) : [];

        return view('admin.concert-bookings.show', $invoicePage + $titlesPage + $mediaPage + $programmePage + $changes->page($concertBooking, $request->string('change_request')->toString() ?: null) + [
            'booking' => $concertBooking,
            'seatsAndFamilies' => $seatsAndFamilies,
            'bookingTab' => $bookingTab,
            'trackerBar' => $request->query('tab') === 'seats-families'
                ? $trackerBar->numbersPage($concertBooking, $request->session()->get('concert_tracker_context', []))
                : $trackerBar->page($concertBooking, $request->session()->get('concert_tracker_context', [])),
            'venues' => Venue::query()->orderBy('name')->get(),
            'studios' => Studio::query()->orderBy('name')->get(['uuid', 'name']),
            'studioStatuses' => StudioStatus::cases(),
            'eventTypes' => EventTypeDefinition::query()->where('system_category', 'concert')->orderBy('name')->get(),
            'contactReview' => $reviewContact->compare($concertBooking, $request->string('studio_uuid')->toString() ?: null),
        ]);
    }

    public function createStudio(CreateStudioFromBookingRequest $request, ConcertBooking $concertBooking, CreateStudioFromBooking $createStudio): RedirectResponse
    {
        $studio = $createStudio->execute($concertBooking, $request->validated(), $request->user());

        return redirect()
            ->route('admin.concert-bookings.show', [$concertBooking, 'studio_uuid' => $studio->uuid])
            ->with('status', 'Studio created from booking details.');
    }

    public function reconcileContact(ReconcileBookingStudioContactRequest $request, ConcertBooking $concertBooking, ReconcileBookingStudioContact $reconcile): RedirectResponse
    {
        $reconcile->execute($concertBooking, $request->validated());

        return redirect()
            ->route('admin.concert-bookings.show', [$concertBooking, 'studio_uuid' => $request->validated('studio_uuid')])
            ->with('status', $request->validated('action') === 'add' ? 'Studio contact added.' : 'Studio contact details updated.');
    }

    public function pending(BookingArtwork $artwork): View
    {
        Gate::authorize('manageScheduling');

        $items = ConcertBookingItem::query()
            ->with(['booking', 'eventTypeDefinition', 'venue', 'schedulingEvent.studio', 'schedulingEvent.competitionContact'])
            ->awaitingApproval()
            ->orderBy('event_date')
            ->orderBy('starts_at')
            ->paginate(50);

        return view('admin.event-management.pending', [
            'items' => $items,
            'artworkUrls' => $artwork->forItems($items->getCollection()),
        ]);
    }

    public function resolveVenue(ResolveConcertBookingVenueRequest $request, ConcertBookingItem $concertBookingItem, ResolveConcertBookingVenue $resolveVenue): RedirectResponse
    {
        $venue = $resolveVenue->execute(
            $concertBookingItem,
            $request->string('resolution_action')->toString(),
            $request->string('venue_uuid')->toString() ?: null,
        );

        return back()->with('status', "Venue resolved as {$venue->name}.");
    }

    public function approve(ReviewConcertBookingRequest $request, ConcertBooking $concertBooking, ApproveBookingForConfirmation $approve): RedirectResponse
    {
        $url = $approve->execute($concertBooking, $request->user(), $request->validated());

        return redirect()->to($url)->with('status', 'Booking approved. Review the confirmation template and send when ready.');
    }

    public function bulkUpdate(BulkUpdateConcertBookingItemsRequest $request, BulkUpdateConcertBookingItems $bulkUpdate): RedirectResponse
    {
        $items = ConcertBookingItem::query()
            ->with(['booking', 'schedulingEvent'])
            ->whereIn('uuid', $request->validated('event_ids'))
            ->get();

        /** @var User $staff */
        $staff = $request->user();
        $count = $bulkUpdate->execute(
            $items,
            $request->string('action')->toString(),
            $staff,
            $request->validated('deadline_date'),
        );

        return back()->with('status', "{$count} event(s) updated.");
    }

    public function update(UpdateConcertBookingRequest $request, ConcertBooking $concertBooking, UpdateConcertBooking $update): RedirectResponse
    {
        $update->execute($concertBooking, $request->validated(), $request->user());

        return back()->with('status', 'Booking details updated.');
    }

    public function cancel(CancelBookingRequest $request, ConcertBooking $concertBooking, ChangeBookingStatus $change): RedirectResponse
    {
        Gate::authorize('manageScheduling');
        $url = $change->execute($concertBooking, $request->user(), $request->validated() + ['status_override' => 'cancelled']);

        return redirect()->to($url)->with('status', 'Booking cancelled. Review and send the studio notification.');
    }

    public function cancelItem(CancelConcertBookingItemRequest $request, ConcertBookingItem $concertBookingItem, CancelConcertBookingItem $cancel): RedirectResponse
    {
        $booking = $concertBookingItem->booking;
        $cancel->execute($concertBookingItem, $request->user());

        return redirect()->route('admin.concert-bookings.show', $booking)->with('status', 'Booking event cancelled.');
    }

    public function storeProgramme(StoreConcertBookingProgrammeRequest $request, ConcertBookingItem $concertBookingItem, StoreConcertBookingProgramme $store, StoreStudioEventFiles $studioFiles): RedirectResponse
    {
        if ($concertBookingItem->schedulingEvent) {
            $studioFiles->execute($concertBookingItem->schedulingEvent, $request->user(), 'programme', [$request->file('programme')]);
        } else {
            $store->execute($concertBookingItem, $request->file('programme'));
        }

        return back()->with('status', 'Event programme attached.');
    }

    public function programme(Request $request, ConcertBookingItem $concertBookingItem, OperationsFileStorage $files): StreamedResponse
    {
        Gate::authorize('manageScheduling');
        abort_unless(filled($concertBookingItem->programme_path), 404);
        abort_unless($files->disk()->exists($concertBookingItem->programme_path), 404);

        return $files->disk()->response(
            $concertBookingItem->programme_path,
            $concertBookingItem->programme_name ?: 'programme.pdf',
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'],
            $files->disk()->mimeType($concertBookingItem->programme_path) === 'application/pdf' ? 'inline' : 'attachment',
        );
    }
}
