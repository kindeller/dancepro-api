<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Models\ConcertVideoBatch;
use App\Features\Programmes\Models\ConcertVideoFile;
use App\Features\Programmes\Requests\ConcertVideoUploadRequest;
use App\Features\Programmes\Services\ConcertVideoUploads;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ConcertVideoUploadController extends Controller
{
    public function begin(ConcertVideoUploadRequest $request, SchedulingEvent $schedulingEvent, ConcertVideoUploads $uploads): JsonResponse
    {
        return ApiResponse::success('Concert video queue registered.', $uploads->summary($uploads->begin($schedulingEvent, $request->user(), $request->validated())));
    }

    public function address(ConcertVideoUploadRequest $request, SchedulingEvent $schedulingEvent, ConcertVideoBatch $batch, ConcertVideoFile $file, ConcertVideoUploads $uploads): JsonResponse
    {
        return ApiResponse::success('Concert video upload address.', $uploads->address($schedulingEvent, $batch, $file, $request->user()))->header('Cache-Control', 'private, no-store');
    }

    public function verify(ConcertVideoUploadRequest $request, SchedulingEvent $schedulingEvent, ConcertVideoBatch $batch, ConcertVideoFile $file, ConcertVideoUploads $uploads): JsonResponse
    {
        $uploads->verify($schedulingEvent, $batch, $file, $request->user());

        return ApiResponse::success('Concert video verified.', $uploads->summary($batch));
    }

    public function complete(ConcertVideoUploadRequest $request, SchedulingEvent $schedulingEvent, ConcertVideoBatch $batch, ConcertVideoUploads $uploads): JsonResponse
    {
        return ApiResponse::success('Finished Uploading recorded. Videos remain private for review.', $uploads->summary($uploads->complete($schedulingEvent, $batch, $request->user())));
    }
}
