<?php

namespace App\Features\Media\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Actions\ManageMediaCatalogue;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Requests\StoreMediaCollectionRequest;
use App\Features\Media\Requests\UpdateMediaCollectionRequest;
use App\Features\Media\Resources\MediaCollectionResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MediaCollectionController extends Controller
{
    public function index(Concert $concert): JsonResponse
    {
        Gate::authorize('viewAny', MediaCollection::class);
        $collections = $concert->mediaCollections()->withCount('assets')->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::success('Media collections returned.', MediaCollectionResource::collection($collections));
    }

    public function store(StoreMediaCollectionRequest $request, Concert $concert, ManageMediaCatalogue $manager): JsonResponse
    {
        Gate::authorize('create', MediaCollection::class);
        /** @var User $user */
        $user = $request->user();
        $collection = $manager->createCollection($concert, $user, $request->validated(), (string) $request->header('Idempotency-Key'));

        return ApiResponse::success('Media collection ready.', new MediaCollectionResource($collection->load('concert')->loadCount('assets')), 201);
    }

    public function show(MediaCollection $collection): JsonResponse
    {
        Gate::authorize('view', $collection);

        return ApiResponse::success('Media collection returned.', new MediaCollectionResource($collection->load('concert')->loadCount('assets')));
    }

    public function update(UpdateMediaCollectionRequest $request, MediaCollection $collection, ManageMediaCatalogue $manager): JsonResponse
    {
        Gate::authorize('update', $collection);

        return ApiResponse::success('Media collection updated.', new MediaCollectionResource($manager->updateCollection($collection, $request->validated())->load('concert')->loadCount('assets')));
    }
}
