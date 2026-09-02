<?php

namespace App\Features\Auth\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorAuthentication
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (config('security.two_factor.enabled') && config('security.two_factor.enforced') && $request->user()?->two_factor_confirmed_at === null) {
            return redirect()->route('account.security')->with('status', 'Set up two-factor authentication to continue.');
        }

        return $next($request);
    }
}
