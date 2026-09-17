<?php

namespace App\Features\Concerts\Controllers;

use App\Features\Concerts\Support\ConcertStatus;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PublicStudioController extends Controller
{
    public function index(): View
    {
        $studios = Studio::query()
            ->where('status', StudioStatus::Active)
            ->whereHas('concerts', fn (Builder $query) => $this->availableConcerts($query))
            ->withCount(['concerts as available_concerts_count' => fn (Builder $query) => $this->availableConcerts($query)])
            ->orderBy('name')
            ->get();

        return view('public.studios.index', compact('studios'));
    }

    public function show(Studio $studio): View
    {
        abort_unless($studio->status === StudioStatus::Active, 404);

        $concerts = $this->availableConcerts($studio->concerts()->getQuery())
            ->orderByDesc('event_date')
            ->get();

        abort_if($concerts->isEmpty(), 404);

        return view('public.studios.show', compact('studio', 'concerts'));
    }

    private function availableConcerts(Builder $query): Builder
    {
        return $query
            ->where('status', ConcertStatus::Published)
            ->where('is_enabled', true)
            ->where(fn (Builder $query) => $query->where('requires_approval', false)->orWhereNotNull('approved_at'))
            ->where(fn (Builder $query) => $query->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('available_until')->orWhere('available_until', '>=', now()));
    }
}
