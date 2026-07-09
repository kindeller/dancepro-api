<?php

namespace App\Features\Competition\Controllers;

use App\Features\Competition\Actions\ListCompetitionObjects;
use App\Features\Competition\Requests\ListCompetitionObjectsRequest;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CompetitionObjectController extends Controller
{
    public function index(
        ListCompetitionObjectsRequest $request,
        ListCompetitionObjects $listCompetitionObjects,
    ): JsonResponse {
        Gate::authorize('viewCompetitionObjects');

        return ApiResponse::success(
            'Competition objects returned.',
            $listCompetitionObjects->handle(
                $request->string('prefix')->toString() ?: null,
                $request->integer('limit') ?: null,
                $request->string('continuation_token')->toString() ?: null,
            ),
        );
    }
}
