<?php

namespace App\Features\Crew\Middleware;

use App\Features\Customers\Support\UserType;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompletedCrewOnboarding
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user?->type === UserType::Crew->value && $user->onboarding_completed_at === null && ! $request->routeIs('crew.profile.*', 'crew.contracts.*')) {
            return redirect()->route('crew.profile.edit')->with('status', 'Please complete your crew profile to continue.');
        }

        return $next($request);
    }
}
