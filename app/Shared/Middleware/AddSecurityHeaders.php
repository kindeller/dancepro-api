<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $cspNonce = base64_encode(random_bytes(18));
        $request->attributes->set('csp_nonce', $cspNonce);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');

        if ($request->is('api/v1', 'api/v1/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        if (config('security.content_security_policy.enabled')) {
            $header = config('security.content_security_policy.report_only')
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($header, $this->contentSecurityPolicy($cspNonce));
        }

        if (app()->environment('production') && parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://maps.googleapis.com https://maps.gstatic.com",
            "script-src-attr 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "media-src 'self' blob: https:",
            "connect-src 'self' https: wss:",
            "frame-src 'self' https:",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
        ]);
    }
}
