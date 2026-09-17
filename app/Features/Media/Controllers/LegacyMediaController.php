<?php

namespace App\Features\Media\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Actions\ManageLegacyMedia;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Requests\ImportLegacyMediaRequest;
use App\Features\Media\Requests\ListLegacyMediaRequest;
use App\Features\Media\Resources\MediaAssetResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LegacyMediaController extends Controller
{
    public function index(ListLegacyMediaRequest $request, Concert $concert, ManageLegacyMedia $legacy): JsonResponse
    {
        Gate::authorize('viewStaffMedia');

        return ApiResponse::success('Legacy media returned.', $legacy->list($concert, $request->string('cursor')->toString() ?: null));
    }

    public function store(ImportLegacyMediaRequest $request, MediaCollection $collection, ManageLegacyMedia $legacy): JsonResponse
    {
        Gate::authorize('view', $collection);
        /** @var User $user */
        $user = $request->user();
        $asset = $legacy->import($collection, $user, $request->validated(), (string) $request->header('Idempotency-Key'));

        return ApiResponse::success('Legacy media asset imported.', new MediaAssetResource($asset->load('collection')), 201);
    }
}
