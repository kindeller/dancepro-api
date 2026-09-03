<?php

namespace App\Features\Crew\Controllers;

use App\Features\Crew\Resources\CrewMobileProfileResource;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->crewProfile->load(['user', 'vehicles']);

        return ApiResponse::success('Crew profile returned.', new CrewMobileProfileResource($profile));
    }
}
