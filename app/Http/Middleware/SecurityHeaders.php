<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request and add defensive HTTP security headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Prevent clickjacking by disallowing framing from third-party origins
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent browser MIME-sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Restrict referrer information to protect query parameters and tokens
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict powerful browser features/permissions
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Legacy XSS protection header for older browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
