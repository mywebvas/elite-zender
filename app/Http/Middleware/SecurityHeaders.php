<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injects security response headers on every request.
 *
 * Required headers (docs/06-SECURITY-COMPLIANCE.md §1):
 *   - Content-Security-Policy     default-src 'self'; SPA-safe subset
 *   - Strict-Transport-Security   HSTS 2yr + includeSubDomains + preload
 *   - X-Frame-Options             DENY — no embedding in iframes
 *   - X-Content-Type-Options      nosniff — no MIME guessing
 *   - Referrer-Policy             strict-origin-when-cross-origin
 *   - Permissions-Policy          minimal — no camera/mic/geoloc by default
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none';"
        );

        // HSTS: 2 years, includeSubDomains, preload (docs/06-SECURITY-COMPLIANCE.md §1)
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=63072000; includeSubDomains; preload'
        );

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()'
        );
        // Remove server fingerprinting headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
