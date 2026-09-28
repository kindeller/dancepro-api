<?php

namespace App\Features\Publicity\Controllers;

use App\Features\Publicity\Requests\ScreenRequest;
use App\Features\Publicity\Requests\SelectScreenEventRequest;
use App\Features\Publicity\Services\ScreenEvents;
use App\Features\Publicity\Services\SlideshowManagement;
use App\Features\Publicity\Services\SlideshowPlaylist;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScreenController extends Controller
{
    public function player(): Response
    {
        return response()->view('publicity.player', ['previewEvent' => null])->header('Cache-Control', 'no-cache');
    }

    public function pair(SlideshowManagement $management): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $management->beginPairing()])->header('Cache-Control', 'no-store');
    }

    public function manifest(ScreenRequest $request, SlideshowPlaylist $playlist): JsonResponse
    {
        $screen = $request->screen();
        $screen->update(['last_seen_at' => now()] + $request->safe()->only(['cached_revision', 'cached_count']));
        if (! $screen->paired_at) {
            return response()->json(['success' => true, 'data' => ['paired' => false]])->header('Cache-Control', 'no-store');
        }
        abort_unless($screen->event, 403);
        $screen->update(['token_expires_at' => now()->addDays(30)]);

        return response()->json(['success' => true, 'data' => ['paired' => true, 'screen_name' => $screen->name] + $playlist->manifest($screen->event)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function events(ScreenRequest $request, ScreenEvents $events): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $events->catalogue($request->screen())])->header('Cache-Control', 'private, no-store');
    }

    public function selectEvent(SelectScreenEventRequest $request, ScreenEvents $events): JsonResponse
    {
        $events->select($request->screen(), $request->validated('event_uuid'));

        return response()->json(['success' => true, 'data' => ['selected' => true]])->header('Cache-Control', 'private, no-store');
    }

    public function image(ScreenRequest $request, string $key, SlideshowPlaylist $playlist, SlideshowManagement $management): StreamedResponse
    {
        $screen = $request->screen();
        abort_unless($screen->paired_at && $screen->event && $screen->event->event_type->value === 'competition' && ! $screen->event->cancelled_at && $management->settings($screen->event)->enabled, 403);

        return Storage::disk('local')->response($playlist->image($screen->event, $key), null, ['Cache-Control' => 'private, no-store']);
    }

    public function video(ScreenRequest $request, string $key, SlideshowPlaylist $playlist, SlideshowManagement $management): StreamedResponse
    {
        $screen = $request->screen();
        abort_unless($screen->paired_at && $screen->event && $screen->event->event_type->value === 'competition' && ! $screen->event->cancelled_at && $management->settings($screen->event)->enabled, 403);

        return Storage::disk('local')->response($playlist->video($screen->event, $key), null, ['Cache-Control' => 'private, no-store', 'Content-Type' => 'video/mp4']);
    }
}
