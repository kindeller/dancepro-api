<?php

namespace App\Shared\Middleware;

use App\Features\Auth\Support\ApiTokenAbility;
use App\Shared\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireExplicitTokenAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $token = $request->user()?->currentAccessToken();
        $granted = $token?->abilities ?? [];

        foreach ($abilities as $ability) {
            $legacyAccess = in_array('*', $granted, true)
                && in_array($ability, ApiTokenAbility::legacyAbilities(), true);

            if (! in_array($ability, $granted, true) && ! $legacyAccess) {
                return ApiResponse::error('This API token does not have the required ability.', status: 403);
            }
        }

        return $next($request);
    }
}
