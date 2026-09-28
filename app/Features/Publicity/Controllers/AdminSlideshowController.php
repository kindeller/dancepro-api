<?php

namespace App\Features\Publicity\Controllers;

use App\Features\Media\Requests\AdminTopPicsRequest;
use App\Features\Media\Services\AdminTopPics;
use App\Features\Publicity\Models\PublicitySlide;
use App\Features\Publicity\Models\TvScreen;
use App\Features\Publicity\Requests\PairScreenRequest;
use App\Features\Publicity\Requests\SavePublicitySlideRequest;
use App\Features\Publicity\Requests\SlideshowIndexRequest;
use App\Features\Publicity\Requests\SlideshowSettingsRequest;
use App\Features\Publicity\Services\SlideshowManagement;
use App\Features\Publicity\Services\SlideshowPlaylist;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSlideshowController extends Controller
{
    public function index(SlideshowIndexRequest $request, AdminTopPics $pics, SlideshowManagement $management): Response
    {
        return response()->view('admin.publicity.index', $management->overview('competition', $pics, $request->validated('advertising_type', 'competition')))->header('Cache-Control', 'private, no-store');
    }

    public function show(AdminTopPicsRequest $request, SchedulingEvent $schedulingEvent, SlideshowManagement $management, SlideshowPlaylist $playlist): Response
    {
        return response()->view('admin.publicity.show', $management->eventPage($schedulingEvent, $playlist))->header('Cache-Control', 'private, no-store');
    }

    public function update(SlideshowSettingsRequest $request, SchedulingEvent $schedulingEvent, SlideshowManagement $management): RedirectResponse
    {
        $management->saveSettings($schedulingEvent, $request->validated());

        return back()->with('status', 'Slideshow settings saved. Connected screens update automatically.');
    }

    public function storeSlide(SavePublicitySlideRequest $request, SlideshowManagement $management, ?SchedulingEvent $schedulingEvent = null): RedirectResponse
    {
        $management->saveSlide($schedulingEvent, $request->validated(), $request->user());

        return back()->with('status', 'Slide added.');
    }

    public function updateSlide(SavePublicitySlideRequest $request, PublicitySlide $slide, SlideshowManagement $management): RedirectResponse
    {
        $management->updateSlide($slide, $request->validated(), $request->user());

        return back()->with('status', 'Slide saved.');
    }

    public function pair(PairScreenRequest $request, SchedulingEvent $schedulingEvent, SlideshowManagement $management): RedirectResponse
    {
        $management->pair($schedulingEvent, $request->validated(), $request->user());

        return back()->with('status', 'Screen paired. Crew can choose any event enabled for TV from the app homepage.');
    }

    public function revoke(AdminTopPicsRequest $request, TvScreen $screen, SlideshowManagement $management): RedirectResponse
    {
        $management->revoke($screen);

        return back()->with('status', 'Screen disconnected. It will stop at its next connection; offline access expires within 24 hours.');
    }

    public function preview(AdminTopPicsRequest $request, SchedulingEvent $schedulingEvent, SlideshowManagement $management): Response
    {
        $management->settings($schedulingEvent);

        return response()->view('publicity.player', ['previewEvent' => $schedulingEvent])->header('Cache-Control', 'private, no-store');
    }

    public function manifest(AdminTopPicsRequest $request, SchedulingEvent $schedulingEvent, SlideshowPlaylist $playlist): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $playlist->manifest($schedulingEvent, true)])->header('Cache-Control', 'private, no-store');
    }

    public function image(AdminTopPicsRequest $request, SchedulingEvent $schedulingEvent, string $key, SlideshowPlaylist $playlist): StreamedResponse
    {
        return Storage::disk('local')->response($playlist->image($schedulingEvent, $key), null, ['Cache-Control' => 'private, no-store']);
    }

    public function video(AdminTopPicsRequest $request, SchedulingEvent $schedulingEvent, string $key, SlideshowPlaylist $playlist): StreamedResponse
    {
        return Storage::disk('local')->response($playlist->video($schedulingEvent, $key), null, ['Cache-Control' => 'private, no-store', 'Content-Type' => 'video/mp4']);
    }
}
