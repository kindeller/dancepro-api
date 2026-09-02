<?php

namespace App\Features\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canAccessAdmin(), 403);

        return $next($request);
    }
}
