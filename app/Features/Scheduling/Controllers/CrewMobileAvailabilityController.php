<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Scheduling\Actions\RecordAvailabilityResponse;
use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Scheduling\Requests\RecordAvailabilityResponseRequest;
use App\Features\Scheduling\Services\CrewMobileAvailability;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileAvailabilityController extends Controller
{
    public function index(Request $request, CrewMobileAvailability $availability): JsonResponse
    {
        return ApiResponse::success('Availability requests returned.', $availability->for($request->user()->crewProfile));
    }

    public function update(RecordAvailabilityResponseRequest $request, SchedulingShift $shift, RecordAvailabilityResponse $record): JsonResponse
    {
        $record->execute($request->user()->crewProfile, $shift, $request->validated());

        return ApiResponse::success('Availability saved.');
    }
}
