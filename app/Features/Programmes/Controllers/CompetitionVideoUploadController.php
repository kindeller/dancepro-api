<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Models\CompetitionVideoBatch;
use App\Features\Programmes\Models\CompetitionVideoFile;
use App\Features\Programmes\Requests\BeginCompetitionVideoBatchRequest;
use App\Features\Programmes\Requests\CompetitionVideoBatchRequest;
use App\Features\Programmes\Requests\ReserveCompetitionVideoRequest;
use App\Features\Programmes\Services\CompetitionVideoUploads;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CompetitionVideoUploadController extends Controller
{
    public function begin(BeginCompetitionVideoBatchRequest $request, SchedulingEvent $schedulingEvent, CompetitionVideoUploads $uploads): JsonResponse
    {
        $batch = $uploads->begin($schedulingEvent, $request->user(), $request->validated());

        return ApiResponse::success('Competition video batch ready.', $uploads->summary($batch))
            ->header('Cache-Control', 'private, no-store');
    }

    public function reserve(ReserveCompetitionVideoRequest $request, SchedulingEvent $schedulingEvent, CompetitionVideoBatch $batch, CompetitionVideoUploads $uploads): JsonResponse
    {
        $result = $uploads->reserve($schedulingEvent, $batch, $request->validated());
        $file = $result['file'];

        return ApiResponse::success('Competition video reserved.', [
            'file_uuid' => $file->uuid, 'item_uuid' => $file->item_uuid,
            'status' => $file->status, 'already_uploaded' => $result['already_uploaded'],
            'needs_assignment' => $file->item_uuid === null, 'upload' => $result['upload'],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function verify(CompetitionVideoBatchRequest $request, SchedulingEvent $schedulingEvent, CompetitionVideoBatch $batch, CompetitionVideoFile $file, CompetitionVideoUploads $uploads): JsonResponse
    {
        $file = $uploads->verify($schedulingEvent, $batch, $file);

        return ApiResponse::success('Competition video verified.', [
            'file_uuid' => $file->uuid, 'item_uuid' => $file->item_uuid, 'status' => $file->status,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function complete(CompetitionVideoBatchRequest $request, SchedulingEvent $schedulingEvent, CompetitionVideoBatch $batch, CompetitionVideoUploads $uploads): JsonResponse
    {
        return ApiResponse::success('Competition video batch completed.', $uploads->complete($schedulingEvent, $batch))
            ->header('Cache-Control', 'private, no-store');
    }
}
