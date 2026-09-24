<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Requests\PhotoUploadProgressRequest;
use App\Features\Programmes\Services\PhotoUploadProgress;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PhotoUploadProgressController extends Controller
{
    public function __invoke(PhotoUploadProgressRequest $request, SchedulingEvent $schedulingEvent, PhotoUploadProgress $progress): JsonResponse
    {
        return ApiResponse::success('Current private photo upload progress.', $progress->forEvent($schedulingEvent, $request->validated('plan_uuid')))
            ->header('Cache-Control', 'private, no-store');
    }
}
