<?php

namespace App\Features\Venues\Controllers;

use App\Features\Venues\Requests\SearchAddressRequest;
use App\Features\Venues\Services\AustralianAddressSearch;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CrewMobileAddressController extends Controller
{
    public function __invoke(SearchAddressRequest $request, AustralianAddressSearch $search): JsonResponse
    {
        return ApiResponse::success('Address suggestions returned.', $search->search($request->string('q')->toString()));
    }
}
