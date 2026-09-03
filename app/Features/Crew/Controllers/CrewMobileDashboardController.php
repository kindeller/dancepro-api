<?php

namespace App\Features\Crew\Controllers;

use App\Features\Crew\Services\CrewMobileDashboard;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileDashboardController extends Controller
{
    public function __invoke(Request $request, CrewMobileDashboard $dashboard): JsonResponse
    {
        return ApiResponse::success('Dashboard returned.', $dashboard->for($request->user()->crewProfile));
    }
}
