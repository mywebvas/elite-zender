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

        $nonce = \Illuminate\Support\Str::random(32);
        \Illuminate\Support\Facades\View::share('cspNonce', $nonce);
        \Illuminate\Support\Facades\Vite::useCspNonce($nonce);

        $csp = "default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none';";

        if (app()->environment('local')) {
            $csp = "default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' http://localhost:5173 http://127.0.0.1:5173 http://[::1]:5173; style-src 'self' 'unsafe-inline' https://fonts.bunny.net http://localhost:5173 http://127.0.0.1:5173 http://[::1]:5173; font-src 'self' https://fonts.bunny.net; img-src 'self' data:; connect-src 'self' ws://localhost:5173 ws://127.0.0.1:5173 ws://[::1]:5173 http://localhost:5173 http://127.0.0.1:5173 http://[::1]:5173; frame-ancestors 'none';";
        }

        $response->headers->set('Content-Security-Policy', $csp);

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
