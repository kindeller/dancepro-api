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
        return response()->view('admin.programmes.competition-customer-preview', $preview->browse($schedulingEvent, $request->validated('folder')))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function image(CompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, string $photo, CompetitionCustomerPreview $preview): StreamedResponse
    {
        return $preview->image($schedulingEvent, $photo, $request->validated('size', 'preview'));
    }
}
