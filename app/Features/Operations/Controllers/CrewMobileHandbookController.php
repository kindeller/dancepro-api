<?php

namespace App\Features\Operations\Controllers;

use App\Features\Operations\Services\CrewMobileHandbook;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CrewMobileHandbookController extends Controller
{
    public function __invoke(CrewMobileHandbook $handbook): JsonResponse
    {
        return ApiResponse::success('Handbook returned.', $handbook->list());
    }
}
