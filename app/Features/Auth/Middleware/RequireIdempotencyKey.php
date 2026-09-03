<?php

namespace App\Features\Auth\Middleware;

use App\Shared\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequireIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || ! Str::isUuid($key)) {
            return ApiResponse::error('The given data was invalid.', [
                'idempotency_key' => ['A valid Idempotency-Key UUID header is required.'],
            ], 422);
        }

        return $next($request);
    }
}
