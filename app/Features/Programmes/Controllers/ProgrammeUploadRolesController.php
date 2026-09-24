<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Services\ProgrammeUploadRoles;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProgrammeUploadRolesController extends Controller
{
    public function __invoke(Request $request, SchedulingEvent $schedulingEvent, ProgrammeUploadRoles $roles): JsonResponse
    {
        Gate::authorize('viewProgramme', $schedulingEvent);

        return ApiResponse::success('Event equipment and media roles.', $roles->forEvent($schedulingEvent, $request->user()))->header('Cache-Control', 'private, no-store');
    }
}
