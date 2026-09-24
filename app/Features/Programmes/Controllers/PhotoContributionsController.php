<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Actions\RegisterPhotoContributions;
use App\Features\Programmes\Requests\RegisterPhotoContributionsRequest;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PhotoContributionsController extends Controller
{
    public function __invoke(RegisterPhotoContributionsRequest $request, SchedulingEvent $schedulingEvent, RegisterPhotoContributions $register): JsonResponse
    {
        return ApiResponse::success('Queued items connected to the photographer. Files have not been uploaded.',
            $register->execute($schedulingEvent, $request->user(), $request->validated()))
            ->header('Cache-Control', 'private, no-store');
    }
}
