<?php

namespace App\Features\Crew\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompletedCrewOnboarding
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user?->canAccessCrew() && ! $user->canAccessAdmin() && $user->onboarding_completed_at === null && ! $request->routeIs('crew.profile.*', 'crew.contracts.*')) {
            return redirect()->route('crew.profile.edit')->with('status', 'Please complete your crew profile to continue.');
        }

        return $next($request);
    }
}
