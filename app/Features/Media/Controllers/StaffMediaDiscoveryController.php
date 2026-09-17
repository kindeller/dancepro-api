<?php

namespace App\Features\Media\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Actions\ListStaffMedia;
use App\Features\Media\Requests\ListStaffConcertsRequest;
use App\Features\Media\Requests\ListStaffStudiosRequest;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StaffMediaDiscoveryController extends Controller
{
    public function studios(ListStaffStudiosRequest $request, ListStaffMedia $list): JsonResponse
    {
        Gate::authorize('viewStaffMedia');
        $paginator = $list->studios($request->string('search')->toString() ?: null, $request->integer('per_page', 50));

        return ApiResponse::success('Staff studios returned.', collect($paginator->items())->map($list->studioData(...))->all(), meta: [
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    public function concerts(ListStaffConcertsRequest $request, ListStaffMedia $list): JsonResponse
    {
        Gate::authorize('viewStaffMedia');
        $paginator = $list->concerts(
            $request->string('studio_uuid')->toString() ?: null,
            $request->string('search')->toString() ?: null,
            $request->integer('per_page', 50),
        );

        return ApiResponse::success('Staff concerts returned.', collect($paginator->items())->map($list->concertData(...))->all(), meta: [
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    public function concert(Concert $concert, ListStaffMedia $list): JsonResponse
    {
        Gate::authorize('viewStaffMedia');

        return ApiResponse::success('Staff concert returned.', $list->concertData($list->concert($concert)));
    }
}
