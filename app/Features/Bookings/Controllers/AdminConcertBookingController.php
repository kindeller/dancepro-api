<?php

namespace App\Features\Bookings\Controllers;

use App\Features\Bookings\Actions\ApproveConcertBooking;
use App\Features\Bookings\Actions\BulkUpdateConcertBookingItems;
use App\Features\Bookings\Actions\CreateStudioFromBooking;
use App\Features\Bookings\Actions\ReconcileBookingStudioContact;
use App\Features\Bookings\Actions\ResolveConcertBookingVenue;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Bookings\Requests\BulkUpdateConcertBookingItemsRequest;
use App\Features\Bookings\Requests\CreateStudioFromBookingRequest;
use App\Features\Bookings\Requests\ReconcileBookingStudioContactRequest;
use App\Features\Bookings\Requests\ResolveConcertBookingVenueRequest;
use App\Features\Bookings\Requests\ReviewConcertBookingRequest;
use App\Features\Bookings\Services\ReviewBookingStudioContact;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use App\Features\Venues\Models\Venue;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminConcertBookingController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageScheduling');

        return view('admin.concert-bookings.index', [
            'items' => ConcertBookingItem::query()
                ->with(['booking', 'eventTypeDefinition', 'schedulingEvent'])
                ->orderBy('event_date')
                ->orderBy('starts_at')
                ->paginate(50),
        ]);
    }

    public function show(Request $request, ConcertBooking $concertBooking, ReviewBookingStudioContact $reviewContact): View
    {
        Gate::authorize('manageScheduling');
        $concertBooking->load(['items.eventTypeDefinition', 'items.schedulingEvent', 'items.venue']);

        return view('admin.concert-bookings.show', [
            'booking' => $concertBooking,
            'venues' => Venue::query()->orderBy('name')->get(),
            'studios' => Studio::query()->orderBy('name')->get(['uuid', 'name']),
            'studioStatuses' => StudioStatus::cases(),
            'contactReview' => $reviewContact->compare($concertBooking, $request->string('studio_uuid')->toString() ?: null),
        ]);
    }

    public function createStudio(CreateStudioFromBookingRequest $request, ConcertBooking $concertBooking, CreateStudioFromBooking $createStudio): RedirectResponse
    {
        $studio = $createStudio->execute($concertBooking, $request->validated());

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

    public function pending(): View
    {
        Gate::authorize('manageScheduling');

        return view('admin.event-management.pending', [
            'items' => ConcertBookingItem::query()
                ->with(['booking', 'eventTypeDefinition', 'venue'])
                ->where('approval_status', 'pending')
                ->orderBy('event_date')
                ->orderBy('starts_at')
                ->paginate(50),
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

    public function approve(ReviewConcertBookingRequest $request, ConcertBooking $concertBooking, ApproveConcertBooking $approveBooking): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $approveBooking->execute($concertBooking, $staff, $request->string('internal_review_note')->toString() ?: null);

        return back()->with('status', 'Booking approved and draft scheduling events created. Availability remains closed.');
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
}
