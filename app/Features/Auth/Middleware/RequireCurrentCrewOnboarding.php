<?php

namespace App\Features\Auth\Middleware;

use App\Features\Crew\Services\CrewOnboardingStatus;
use App\Shared\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCurrentCrewOnboarding
{
    public function __construct(private readonly CrewOnboardingStatus $status) {}

    public function handle(Request $request, Closure $next): Response
    {
        $profile = $request->user()?->crewProfile;

        if ($profile === null || ! $this->status->for($profile)['complete']) {
            return ApiResponse::error('Complete your Crew onboarding to continue.', [
                'onboarding' => ['required'],
            ], 403);
        }

        return $next($request);
    }
}
