<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Actions\FinishPhotoUploads;
use App\Features\Programmes\Requests\FinishPhotoUploadsRequest;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PhotoCompletionController extends Controller
{
    public function __invoke(FinishPhotoUploadsRequest $request, SchedulingEvent $schedulingEvent, FinishPhotoUploads $finish): JsonResponse
    {
        return ApiResponse::success('Photographer completion recorded. Gallery remains private.',
            $finish->execute($schedulingEvent, $request->user(), $request->validated()))
            ->header('Cache-Control', 'private, no-store');
    }
}
