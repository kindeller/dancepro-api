<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Requests\ProgrammeChatUpdatesRequest;
use App\Features\Programmes\Services\NotifySharedProgramme;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProgrammeChatUpdatesController extends Controller
{
    public function __invoke(ProgrammeChatUpdatesRequest $request, SchedulingEvent $schedulingEvent, NotifySharedProgramme $notify): JsonResponse
    {
        return ApiResponse::success('Programme updates.', $notify->updates($schedulingEvent))->header('Cache-Control', 'private, no-store');
    }
}
