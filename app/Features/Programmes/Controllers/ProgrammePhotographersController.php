<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Services\ProgrammePhotographers;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProgrammePhotographersController extends Controller
{
    public function __invoke(SchedulingEvent $schedulingEvent, ProgrammePhotographers $photographers): JsonResponse
    {
        Gate::authorize('viewProgramme', $schedulingEvent);

        return ApiResponse::success('Published photographer assignments.', $photographers->forEvent($schedulingEvent))
            ->header('Cache-Control', 'private, no-store');
    }
}
