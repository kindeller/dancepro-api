<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Requests\SharedConcertProgrammeRequest;
use App\Features\Programmes\Services\SharedConcertProgramme;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class SharedConcertProgrammeController extends Controller
{
    public function __invoke(SharedConcertProgrammeRequest $request, SchedulingEvent $schedulingEvent, SharedConcertProgramme $shared): JsonResponse
    {
        return ApiResponse::success('Shared concert programme.', $shared->forEvent($schedulingEvent))->header('Cache-Control', 'private, no-store');
    }
}
