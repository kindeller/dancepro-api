<?php

namespace App\Features\Media\Controllers;

use App\Features\Media\Requests\AdminTopPicsRequest;
use App\Features\Media\Requests\ChooseTopPicCoverRequest;
use App\Features\Media\Services\AdminConcertAlbums;
use App\Features\Media\Services\AdminTopPics;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTopPicsController extends Controller
{
    public function index(AdminTopPicsRequest $request, AdminTopPics $pics, AdminConcertAlbums $albums): Response
    {
        return response()->view('admin.media.top-pics', $request->validated('type', 'competition') === 'concert'
            ? $albums->browse($request->validated('studio'), $request->boolean('unlinked'), true) + ['type' => 'concert']
            : $pics->events('competition'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function cover(ChooseTopPicCoverRequest $request, SchedulingEvent $schedulingEvent, AdminTopPics $pics): JsonResponse|RedirectResponse
    {
        $pics->chooseCover($schedulingEvent, $request->validated('photo_uuid'), $request->user());
        $message = $request->validated('photo_uuid') ? 'Event cover saved.' : 'Automatic event cover restored.';

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'photo_uuid' => $request->validated('photo_uuid')])
            : back()->with('status', $message);
    }

    public function show(AdminTopPicsRequest $request, SchedulingEvent $schedulingEvent, AdminTopPics $pics): Response
    {
        return response()->view('admin.media.top-pics-album', $pics->album($schedulingEvent))
            ->header('Cache-Control', 'private, no-store');
    }

    public function download(AdminTopPicsRequest $request, SchedulingEvent $schedulingEvent, string $photo, AdminTopPics $pics): StreamedResponse
    {
        return $pics->download($schedulingEvent, $photo, $request->user());
    }
}
