<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Requests\CompetitionGalleryRequest;
use App\Features\Programmes\Services\CompetitionCustomerPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompetitionCustomerPreviewController extends Controller
{
    public function show(CompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CompetitionCustomerPreview $preview): Response
    {
        $previewRoute = 'admin.competition-gallery.customer-preview';
        $gallery = $preview->browse($schedulingEvent, $request->validated('folder'));

        return response()->view('customers.competitions.gallery', $gallery + [
            'shopping' => ['selections' => collect(), 'owned' => ['routine_photos' => [], 'routine_videos' => [], 'packages' => [], 'keys' => []]],
            'videoAvailable' => false,
            'packagePrice' => 4500,
            'focusPhoto' => null,
            'activeTab' => 'event',
            'studioLogoUrl' => null,
            'cartCount' => 0,
            'favouriteCount' => 0,
            'adminPreview' => true,
            'competitionHomeUrl' => route('admin.competition-gallery.show', $schedulingEvent),
            'competitionRoutes' => [
                'home' => 'admin.competition-gallery.show',
                'show' => $previewRoute,
                'image' => 'admin.competition-gallery.customer-preview.image',
                'selection' => $previewRoute,
                'batch' => $previewRoute,
            ],
            'competitionTabRoutes' => [
                'event' => $previewRoute,
                'dancers' => $previewRoute,
                'studios' => $previewRoute,
                'improvisation' => $previewRoute,
                'favourites' => $previewRoute,
                'cart' => $previewRoute,
            ],
        ])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function image(CompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, string $photo, CompetitionCustomerPreview $preview): StreamedResponse
    {
        return $preview->image($schedulingEvent, $photo, $request->validated('size', 'preview'));
    }
}
