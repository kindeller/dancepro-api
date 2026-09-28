<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Models\GuestGalleryRegistration;
use App\Features\Customers\Requests\GuestGalleryRegistrationRequest;
use App\Features\Customers\Services\GuestGalleryRegistrations;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GuestGalleryRegistrationController extends Controller
{
    public function store(GuestGalleryRegistrationRequest $request, SchedulingEvent $schedulingEvent, GuestGalleryRegistrations $registrations): RedirectResponse
    {
        $registrations->register($schedulingEvent, $request->user()?->email ?? $request->validated('email'), $request->user());

        return back()->with('status', $request->user()
            ? 'We’ll notify you here and by email when this gallery is ready.'
            : 'You’re registered. We’ll email you when this gallery is ready.');
    }

    public function open(Request $request, GuestGalleryRegistration $registration): RedirectResponse
    {
        $request->session()->put('guest_gallery_prefill_email', $registration->email);
        $event = $registration->event()->with('concertBookingItem.concert')->firstOrFail();
        if ($event->event_type->value === 'competition') {
            return to_route('guest-galleries.competitions.access', $event);
        }
        $concert = $event->concertBookingItem?->concert;
        abort_unless($concert, 404);

        return to_route('guest-galleries.concerts.access', $concert);
    }

    public function unsubscribe(Request $request, GuestGalleryRegistration $registration, GuestGalleryRegistrations $registrations): Response
    {
        $registrations->unsubscribe($registration);

        return response()->view('customers.guest-galleries.unsubscribed', ['concert' => null, 'studio' => null]);
    }
}
