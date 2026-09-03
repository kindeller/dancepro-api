<?php

namespace App\Features\Crew\Controllers;

use App\Features\Crew\Services\CrewMobileDirectory;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileDirectoryController extends Controller
{
    public function __invoke(Request $request, CrewMobileDirectory $directory): JsonResponse
    {
        return ApiResponse::success('Directory returned.', $directory->for($request->user()));
    }
}
