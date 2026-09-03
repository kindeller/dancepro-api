<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Scheduling\Actions\AcceptShiftCoverRequest;
use App\Features\Scheduling\Actions\CreateShiftCoverRequest;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Models\ShiftCoverRequest;
use App\Features\Scheduling\Requests\StoreShiftCoverRequest;
use App\Features\Scheduling\Resources\CrewCoverRequestResource;
use App\Features\Scheduling\Services\CrewMobileCoverRequests;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileCoverRequestController extends Controller
{
    public function index(Request $request, CrewMobileCoverRequests $coverRequests): JsonResponse
    {
        $requests = $coverRequests->for($request->user()->crewProfile);

        return ApiResponse::success('Cover requests returned.', [
            'received' => CrewCoverRequestResource::collection($requests['received']),
            'sent' => CrewCoverRequestResource::collection($requests['sent']),
        ]);
    }

    public function store(StoreShiftCoverRequest $request, SchedulingShiftAssignment $assignment, CreateShiftCoverRequest $createCover, CrewMobileCoverRequests $coverRequests): JsonResponse
    {
        $coverRequest = $createCover->execute($assignment, $request->user()->crewProfile, $request->validated('recipients'), $request->validated('message'));

        return ApiResponse::success('Cover request sent.', new CrewCoverRequestResource(
            $coverRequests->findVisible($request->user()->crewProfile, $coverRequest),
        ));
    }

    public function accept(Request $request, ShiftCoverRequest $coverRequest, AcceptShiftCoverRequest $acceptCover, CrewMobileCoverRequests $coverRequests): JsonResponse
    {
        $coverRequests->findReceived($request->user()->crewProfile, $coverRequest);
        $assignment = $acceptCover->execute($coverRequest, $request->user()->crewProfile);

        return ApiResponse::success('Cover accepted.', [
            'cover_request' => new CrewCoverRequestResource($coverRequests->findVisible($request->user()->crewProfile, $coverRequest)),
            'assignment_id' => $assignment->uuid,
        ]);
    }
}
