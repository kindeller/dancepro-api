<?php

namespace App\Features\Downloads\Controllers;

use App\Features\Downloads\Actions\CreateDownloadLinks;
use App\Features\Downloads\Actions\RevokeDownloadLink;
use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Requests\CreateDownloadLinksRequest;
use App\Features\Downloads\Requests\RevokeDownloadLinkRequest;
use App\Features\Downloads\Resources\DownloadAccessResource;
use App\Features\Downloads\Resources\DownloadLinkResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DownloadLinkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DownloadLink::class);

        $downloadLinks = DownloadLink::query()
            ->latest()
            ->paginate(50);

        return ApiResponse::success(
            'Download links returned.',
            DownloadLinkResource::collection($downloadLinks)->response()->getData(true)['data'],
            meta: [
                'current_page' => $downloadLinks->currentPage(),
                'last_page' => $downloadLinks->lastPage(),
                'per_page' => $downloadLinks->perPage(),
                'total' => $downloadLinks->total(),
            ],
        );
    }

    public function store(CreateDownloadLinksRequest $request, CreateDownloadLinks $createDownloadLinks): JsonResponse
    {
        Gate::authorize('create', DownloadLink::class);

        /** @var User $user */
        $user = $request->user();

        $items = $createDownloadLinks
            ->handle(
                $user,
                $request->array('keys'),
                $request->string('disk')->toString() ?: null,
                $request->integer('days') ?: null,
                $request->string('purpose')->toString() ?: null,
                $request->string('notes')->toString() ?: null,
            )
            ->map(fn (array $item): array => [
                'uuid' => $item['download_link']->uuid,
                'key' => $item['download_link']->storage_key,
                'url' => route('downloads.public.show', ['token' => $item['token']]),
                'expires_at' => $item['download_link']->expires_at?->toISOString(),
                'status' => $item['download_link']->status,
            ])
            ->all();

        return ApiResponse::success('Download links created.', $items, 201);
    }

    public function show(DownloadLink $downloadLink): JsonResponse
    {
        Gate::authorize('view', $downloadLink);

        return ApiResponse::success('Download link returned.', new DownloadLinkResource($downloadLink));
    }

    public function revoke(
        RevokeDownloadLinkRequest $request,
        DownloadLink $downloadLink,
        RevokeDownloadLink $revokeDownloadLink,
    ): JsonResponse {
        Gate::authorize('revoke', $downloadLink);

        /** @var User $user */
        $user = $request->user();

        $downloadLink = $revokeDownloadLink->handle(
            $downloadLink,
            $user,
            $request->string('reason')->toString() ?: null,
        );

        return ApiResponse::success('Download link revoked.', new DownloadLinkResource($downloadLink));
    }

    public function accesses(DownloadLink $downloadLink): JsonResponse
    {
        Gate::authorize('view', $downloadLink);

        $accesses = $downloadLink->accesses()
            ->latest('accessed_at')
            ->paginate(50);

        return ApiResponse::success(
            'Download link accesses returned.',
            DownloadAccessResource::collection($accesses)->response()->getData(true)['data'],
            meta: [
                'current_page' => $accesses->currentPage(),
                'last_page' => $accesses->lastPage(),
                'per_page' => $accesses->perPage(),
                'total' => $accesses->total(),
            ],
        );
    }
}
