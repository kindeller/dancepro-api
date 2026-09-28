<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Requests\DownloadConcertEditingFileRequest;
use App\Features\Tracking\Services\ConcertEditingDownloads;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConcertEditingDownloadController extends Controller
{
    public function __invoke(DownloadConcertEditingFileRequest $request, SchedulingEvent $schedulingEvent, string $kind, ConcertEditingDownloads $downloads, ?string $file = null): StreamedResponse
    {
        return $downloads->download($schedulingEvent, $request->user(), $kind, $file);
    }
}
