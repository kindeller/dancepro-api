<?php

namespace App\Features\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CrewMobileAppConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success('App configuration returned.', config('mobile.app'));
    }
}
