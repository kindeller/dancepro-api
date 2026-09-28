<?php

namespace App\Features\Publicity\Controllers;

use App\Features\Publicity\Requests\GallerySharingRequest;
use App\Features\Publicity\Services\GallerySharing;
use App\Features\Publicity\Services\PublicityEventAccess;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class AdminGallerySharingController extends Controller
{
    public function index(GallerySharingRequest $request, GallerySharing $sharing): Response
    {
        return response()->view('admin.publicity.sharing', $sharing->catalogue($request->validated()))->header('Cache-Control', 'private, no-store');
    }

    public function eventQr(GallerySharingRequest $request, SchedulingEvent $schedulingEvent, GallerySharing $sharing, PublicityEventAccess $access): Response
    {
        abort_unless($schedulingEvent->event_type->value === 'competition', 404);

        return response($sharing->qr($access->link($schedulingEvent)), 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dancepro-event-'.$schedulingEvent->uuid.'.png"', 'Cache-Control' => 'private, no-store']);
    }

    public function studioQr(GallerySharingRequest $request, Studio $studio, GallerySharing $sharing, PublicityEventAccess $access): Response
    {
        return response($sharing->qr($access->studioLink($studio)), 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dancepro-studio-'.$studio->uuid.'.png"', 'Cache-Control' => 'private, no-store']);
    }
}
