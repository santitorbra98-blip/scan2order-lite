<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // API-only backend: no inline scripts are served.
        // 'unsafe-inline' was removed from script-src to eliminate the XSS bypass it introduces.
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; " .
            "script-src 'none'; " .
            "connect-src 'self'; " .
            "img-src 'self' data: https:; " .
            "style-src 'none'; " .
            "font-src 'none';"
        );

        // HSTS: always emit in production so downstream CDN/browsers cache the policy.
        if (app()->environment('production') || $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
