<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Actions\SyncPhotoTopPics;
use App\Features\Programmes\Requests\SyncPhotoTopPicsRequest;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PhotoTopPicsController extends Controller
{
    public function __invoke(SyncPhotoTopPicsRequest $request, SchedulingEvent $schedulingEvent, SyncPhotoTopPics $sync): JsonResponse
    {
        return ApiResponse::success('Top Pics synced.', $sync->execute($schedulingEvent, $request->user(), $request->validated('changes')))
            ->header('Cache-Control', 'private, no-store');
    }
}
