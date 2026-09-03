<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Resources\CrewAssignmentDetailResource;
use App\Features\Scheduling\Resources\CrewAssignmentResource;
use App\Features\Scheduling\Services\CrewMobileAssignments;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileAssignmentController extends Controller
{
    public function index(Request $request, CrewMobileAssignments $assignments): JsonResponse
    {
        $scope = in_array($request->string('scope')->toString(), ['upcoming', 'past', 'all'], true)
            ? $request->string('scope')->toString() : 'upcoming';
        $limit = min(max($request->integer('limit', 25), 1), 100);
        $page = $assignments->paginate($request->user()->crewProfile, $scope, $limit);

        return ApiResponse::success('Assignments returned.', CrewAssignmentResource::collection($page->items()), meta: [
            'next_cursor' => $page->nextCursor()?->encode(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    public function show(Request $request, SchedulingShiftAssignment $assignment, CrewMobileAssignments $assignments): JsonResponse
    {
        $assignment = $assignments->findFor($request->user()->crewProfile, $assignment);

        return ApiResponse::success('Assignment returned.', new CrewAssignmentDetailResource($assignment));
    }
}
