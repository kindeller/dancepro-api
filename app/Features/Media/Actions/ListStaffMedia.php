<?php

namespace App\Features\Media\Actions;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Studios\Models\Studio;
use Illuminate\Contracts\Pagination\CursorPaginator;

class ListStaffMedia
{
    public function studios(?string $search, int $perPage): CursorPaginator
    {
        return Studio::query()
            ->when($search, fn ($query, string $value) => $query->where('name', 'like', '%'.$value.'%'))
            ->withCount('concerts')
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($perPage);
    }

    public function concerts(?string $studioUuid, ?string $search, int $perPage): CursorPaginator
    {
        return Concert::query()
            ->with('studio')
            ->withCount([
                'mediaCollections',
                'mediaCollections as available_assets_count' => fn ($query) => $query
                    ->join('media_assets', 'media_assets.media_collection_id', '=', 'media_collections.id')
                    ->where('media_assets.status', MediaAssetStatus::Available->value),
                'mediaCollections as processing_assets_count' => fn ($query) => $query
                    ->join('media_assets', 'media_assets.media_collection_id', '=', 'media_collections.id')
                    ->where('media_assets.status', MediaAssetStatus::Processing->value),
            ])
            ->when($studioUuid, fn ($query, string $uuid) => $query->whereHas('studio', fn ($studio) => $studio->where('uuid', $uuid)))
            ->when($search, fn ($query, string $value) => $query->where('name', 'like', '%'.$value.'%'))
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function concert(Concert $concert): Concert
    {
        return $concert->load('studio')->loadCount([
            'mediaCollections',
            'mediaCollections as available_assets_count' => fn ($query) => $query
                ->join('media_assets', 'media_assets.media_collection_id', '=', 'media_collections.id')
                ->where('media_assets.status', MediaAssetStatus::Available->value),
            'mediaCollections as processing_assets_count' => fn ($query) => $query
                ->join('media_assets', 'media_assets.media_collection_id', '=', 'media_collections.id')
                ->where('media_assets.status', MediaAssetStatus::Processing->value),
        ]);
    }

    public function studioData(Studio $studio): array
    {
        return [
            'uuid' => $studio->uuid,
            'name' => $studio->name,
            'slug' => $studio->slug,
            'status' => $studio->status,
            'concert_count' => $studio->concerts_count,
        ];
    }

    public function concertData(Concert $concert): array
    {
        return [
            'uuid' => $concert->uuid,
            'name' => $concert->name,
            'slug' => $concert->slug,
            'status' => $concert->status,
            'event_date' => $concert->event_date,
            'is_enabled' => $concert->is_enabled,
            'studio' => [
                'uuid' => $concert->studio->uuid,
                'name' => $concert->studio->name,
            ],
            'media_summary' => [
                'collections' => $concert->media_collections_count,
                'available_assets' => $concert->available_assets_count,
                'processing_assets' => $concert->processing_assets_count,
            ],
        ];
    }
}
