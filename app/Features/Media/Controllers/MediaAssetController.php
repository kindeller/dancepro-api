<?php

namespace App\Features\Media\Controllers;

use App\Features\Media\Actions\ManageMediaCatalogue;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Requests\StoreMediaAssetRequest;
use App\Features\Media\Requests\UpdateMediaAssetRequest;
use App\Features\Media\Resources\MediaAssetResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MediaAssetController extends Controller
{
    public function index(MediaCollection $collection): JsonResponse
    {
        Gate::authorize('view', $collection);
        $assets = $collection->assets()->orderBy('sort_order')->orderBy('id')->get()->load('collection');

        return ApiResponse::success('Media assets returned.', MediaAssetResource::collection($assets));
    }

    public function store(StoreMediaAssetRequest $request, MediaCollection $collection, ManageMediaCatalogue $manager): JsonResponse
    {
        Gate::authorize('create', MediaAsset::class);
        Gate::authorize('view', $collection);
        /** @var User $user */
        $user = $request->user();
        $asset = $manager->reserveAsset($collection, $user, $request->validated(), (string) $request->header('Idempotency-Key'));

        return ApiResponse::success('Media asset reserved.', new MediaAssetResource($asset->load('collection')), 201);
    }

    public function show(MediaAsset $asset): JsonResponse
    {
        Gate::authorize('view', $asset);

        return ApiResponse::success('Media asset returned.', new MediaAssetResource($asset->load('collection')));
    }

    public function update(UpdateMediaAssetRequest $request, MediaAsset $asset, ManageMediaCatalogue $manager): JsonResponse
    {
        Gate::authorize('update', $asset);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success('Media asset updated.', new MediaAssetResource($manager->updateAsset($asset, $user, $request->validated())->load('collection')));
    }
}
