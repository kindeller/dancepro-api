<?php

namespace App\Features\Customers\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Requests\GuestGalleryUnlockRequest;
use App\Features\Customers\Services\GuestGalleryAccessManager;
use App\Features\Customers\Services\GuestGalleryCatalogue;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GuestGalleryAccessController extends Controller
{
    public function competition(Request $request, SchedulingEvent $schedulingEvent, GuestGalleryCatalogue $catalogue): Response
    {
        abort_unless($catalogue->competitionIsReleased($schedulingEvent), 404);

        return $this->view($request, 'competition', $schedulingEvent->name, $schedulingEvent, null);
    }

    public function unlockCompetition(GuestGalleryUnlockRequest $request, SchedulingEvent $schedulingEvent, GuestGalleryAccessManager $access): RedirectResponse
    {
        $access->unlockCompetition($request, $schedulingEvent, $request->validated('email'), $request->validated('login_code'));

        return to_route('guest-galleries.competitions.show', $schedulingEvent);
    }

    public function studio(Request $request, Studio $studio, GuestGalleryCatalogue $catalogue, GuestGalleryAccessManager $access): Response|RedirectResponse
    {
        abort_unless($catalogue->concerts($studio)->contains('released', true), 404);
        if ($access->studioAccess($request, $studio)) {
            return to_route('guest-galleries.studios.show', $studio);
        }

        return response()->view('customers.guest-galleries.access', [
            'galleryType' => 'concert', 'galleryTitle' => $studio->name, 'studio' => $studio,
            'event' => null, 'concert' => null,
            'prefillEmail' => $request->session()->get('guest_gallery_prefill_email', ''),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function unlockStudio(GuestGalleryUnlockRequest $request, Studio $studio, GuestGalleryAccessManager $access): RedirectResponse
    {
        $access->unlockStudio($request, $studio, $request->validated('email'), $request->validated('login_code'));

        return to_route('guest-galleries.studios.show', $studio);
    }

    public function concert(Request $request, Concert $concert, GuestGalleryCatalogue $catalogue, GuestGalleryAccessManager $access): RedirectResponse
    {
        abort_unless($catalogue->concertIsReleased($concert), 404);

        return $access->studioAccess($request, $concert->studio)
            ? to_route('guest-galleries.concerts.show', $concert)
            : to_route('guest-galleries.studios.access', $concert->studio);
    }

    public function unlockConcert(GuestGalleryUnlockRequest $request, Concert $concert, GuestGalleryAccessManager $access): RedirectResponse
    {
        $access->unlockConcert($request, $concert, $request->validated('email'), $request->validated('login_code'));

        return to_route('guest-galleries.concerts.show', $concert);
    }

    private function view(Request $request, string $type, string $title, ?SchedulingEvent $event, ?Concert $concert): Response
    {
        return response()->view('customers.guest-galleries.access', [
            'galleryType' => $type, 'galleryTitle' => $title, 'event' => $event, 'concert' => $concert,
            'studio' => $concert?->studio, 'prefillEmail' => $request->session()->get('guest_gallery_prefill_email', ''),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
