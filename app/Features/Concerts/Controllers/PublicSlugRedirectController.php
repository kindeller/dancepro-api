<?php

namespace App\Features\Concerts\Controllers;

use App\Features\Concerts\Actions\ResolveConcertBySlug;
use App\Features\Studios\Actions\ResolveStudioBySlug;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class PublicSlugRedirectController extends Controller
{
    public function studio(string $slug, ResolveStudioBySlug $resolveStudio): RedirectResponse
    {
        return redirect()->route('studios.show', $resolveStudio->execute($slug));
    }

    public function concert(string $slug, ResolveConcertBySlug $resolveConcert): RedirectResponse
    {
        return redirect()->route('concerts.show', $resolveConcert->execute($slug));
    }
}
