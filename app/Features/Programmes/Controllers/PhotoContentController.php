<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Actions\ReceiveProgrammePhoto;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Requests\UploadProgrammePhotoRequest;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PhotoContentController extends Controller
{
    public function __invoke(UploadProgrammePhotoRequest $request, SchedulingEvent $schedulingEvent, ProgrammePhotoFile $photoFile, ReceiveProgrammePhoto $receive): JsonResponse
    {
        return ApiResponse::success('Photo received and verified. Gallery remains private.',
            $receive->execute($schedulingEvent, $photoFile, $request->user(), $request->getContent(true)))
            ->header('Cache-Control', 'private, no-store');
    }
}
