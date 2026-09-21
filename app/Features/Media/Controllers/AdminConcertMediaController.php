<?php

namespace App\Features\Media\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminConcertMediaController extends Controller
{
    public function __invoke(Concert $concert): View
    {
        Gate::authorize('manageConcerts');

        $concert->load([
            'mediaCollections' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'mediaCollections.assets' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        return view('admin.concerts.media', compact('concert'));
    }
}
