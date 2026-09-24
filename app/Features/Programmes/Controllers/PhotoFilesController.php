<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Actions\ReservePhotoFiles;
use App\Features\Programmes\Requests\ReservePhotoFilesRequest;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PhotoFilesController extends Controller
{
    public function __invoke(ReservePhotoFilesRequest $request, SchedulingEvent $schedulingEvent, ReservePhotoFiles $reserve): JsonResponse
    {
        return ApiResponse::success('Photo files reserved. No files have been received.',
            $reserve->execute($schedulingEvent, $request->user(), $request->validated()))
            ->header('Cache-Control', 'private, no-store');
    }
}
