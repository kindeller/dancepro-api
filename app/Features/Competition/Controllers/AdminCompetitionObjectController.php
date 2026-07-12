<?php

namespace App\Features\Competition\Controllers;

use App\Features\Competition\Actions\ListCompetitionObjects;
use App\Features\Competition\Requests\ListCompetitionObjectsRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminCompetitionObjectController extends Controller
{
    private const DEFAULT_CHUNK_SIZE = 250;

    public function index(
        ListCompetitionObjectsRequest $request,
        ListCompetitionObjects $listCompetitionObjects,
    ): View {
        Gate::authorize('viewCompetitionObjects');

        $objects = $listCompetitionObjects->handle(
            $request->string('prefix')->toString() ?: null,
            $request->integer('limit') ?: self::DEFAULT_CHUNK_SIZE,
            $request->string('continuation_token')->toString() ?: null,
        );

        return view('admin.competition.objects.index', [
            'objects' => $objects,
            'prefix' => $objects['prefix'],
            'limit' => $objects['pagination']['limit'],
        ]);
    }

    public function chunk(
        ListCompetitionObjectsRequest $request,
        ListCompetitionObjects $listCompetitionObjects,
    ): JsonResponse {
        Gate::authorize('viewCompetitionObjects');

        $objects = $listCompetitionObjects->handle(
            $request->string('prefix')->toString() ?: null,
            $request->integer('limit') ?: self::DEFAULT_CHUNK_SIZE,
            $request->string('continuation_token')->toString() ?: null,
        );

        return response()->json([
            'success' => true,
            'data' => $objects,
        ]);
    }
}
