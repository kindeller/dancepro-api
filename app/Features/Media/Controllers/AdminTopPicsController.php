<?php

namespace App\Features\Media\Controllers;

use App\Features\Media\Requests\AdminTopPicsRequest;
use App\Features\Media\Services\AdminTopPics;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTopPicsController extends Controller
{
    public function index(AdminTopPicsRequest $request, AdminTopPics $pics): Response
    {
        return response()->view('admin.media.top-pics', $pics->events($request->validated('type', 'competition')))
            ->header('Cache-Control', 'private, no-store');
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
