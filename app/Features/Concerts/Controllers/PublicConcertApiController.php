<?php

namespace App\Features\Concerts\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Support\ConcertStatus;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class PublicConcertApiController extends Controller
{
    public function studios(): JsonResponse
    {
        $studios = Studio::query()
            ->where('status', StudioStatus::Active)
            ->whereHas('concerts', fn (Builder $query) => $this->available($query))
            ->orderBy('name')
            ->get()
            ->map(fn (Studio $studio) => [
                'uuid' => $studio->uuid,
                'name' => $studio->name,
                'description' => $studio->description,
                'cover_image_url' => $studio->cover_image_url,
                'brand_color' => $studio->brand_color,
            ]);

        return ApiResponse::success('Studios returned.', $studios);
    }

    public function studio(Studio $studio): JsonResponse
    {
        abort_unless($studio->status === StudioStatus::Active, 404);

        $concerts = $this->available($studio->concerts()->getQuery())
            ->orderByDesc('event_date')
            ->get()
            ->map(fn (Concert $concert) => $this->concertData($concert));

        abort_if($concerts->isEmpty(), 404);

        return ApiResponse::success('Studio concerts returned.', [
            'studio' => [
                'uuid' => $studio->uuid,
                'name' => $studio->name,
                'description' => $studio->description,
                'cover_image_url' => $studio->cover_image_url,
                'brand_color' => $studio->brand_color,
            ],
            'concerts' => $concerts,
        ]);
    }

    public function concert(Concert $concert): JsonResponse
    {
        abort_unless($concert->isPubliclyAvailable(), 404);

        return ApiResponse::success('Concert returned.', $this->concertData($concert->load('studio')) + [
            'studio' => ['uuid' => $concert->studio->uuid, 'name' => $concert->studio->name],
        ]);
    }

    private function concertData(Concert $concert): array
    {
        return [
            'uuid' => $concert->uuid,
            'name' => $concert->name,
            'description' => $concert->description,
            'event_date' => $concert->event_date?->toDateString(),
            'event_end_date' => $concert->event_end_date?->toDateString(),
            'venue_name' => $concert->venue_name,
            'cover_image_url' => $concert->cover_image_url,
            'brand_color' => $concert->brand_color,
            'requires_password' => $concert->requiresPassword(),
            'program_available' => $concert->program_url !== null,
            'gallery_available' => $concert->external_gallery_url !== null,
        ];
    }

    private function available(Builder $query): Builder
    {
        return $query
            ->where('status', ConcertStatus::Published)
            ->where('is_enabled', true)
            ->where(fn (Builder $query) => $query->where('requires_approval', false)->orWhereNotNull('approved_at'))
            ->where(fn (Builder $query) => $query->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('available_until')->orWhere('available_until', '>=', now()));
    }
}
