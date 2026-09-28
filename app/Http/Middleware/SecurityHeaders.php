<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injects security response headers on every request.
 *
 * Required headers (docs/06-SECURITY-COMPLIANCE.md §1):
 *   - Content-Security-Policy     per-request nonce, no third-party origins
 *   - Strict-Transport-Security   HSTS 2yr + includeSubDomains + preload
 *   - X-Frame-Options             DENY — no embedding in iframes
 *   - X-Content-Type-Options      nosniff — no MIME guessing
 *   - Referrer-Policy             strict-origin-when-cross-origin
 *   - Permissions-Policy          minimal — no camera/mic/geolocation
 *   - Cross-Origin-*-Policy       isolate the browsing context
 */
class SecurityHeaders
{
    /** Vite dev-server origins, allow-listed only in local development. */
    private const DEV_ORIGINS = [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://[::1]:5173',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // The nonce has to exist *before* the response renders, otherwise the
        // inline theme-flash script in the layout is emitted without one and
        // the browser blocks it.
        $nonce = Str::random(32);

        View::share('cspNonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        foreach ($this->headers($nonce) as $header => $value) {
            $response->headers->set($header, $value);
        }

        // Strip server fingerprinting.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }

    /** @return array<string, string> */
    private function headers(string $nonce): array
    {
        return [
            'Content-Security-Policy' => $this->contentSecurityPolicy($nonce),
            'Strict-Transport-Security' => 'max-age=63072000; includeSubDomains; preload',
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'accelerometer=(), camera=(), display-capture=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $isLocal = app()->environment('local');

        // Fonts are self-hosted by the Vite font plugin at build time, so the
        // fonts.bunny.net exceptions that used to sit in style-src/font-src
        // are gone: no third-party origin remains in the policy.
        $directives = [
            'default-src' => ["'self'"],
            // 'unsafe-eval' is required by Alpine.js, which compiles x-*
            // expressions at runtime. Removing it means adopting Alpine's CSP
            // build and rewriting every inline expression — tracked as a
            // follow-up in docs/06.
            'script-src' => ["'self'", "'nonce-{$nonce}'", "'unsafe-eval'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'font-src' => ["'self'", 'data:'],
            // data: covers the tracking pixel and inline SVG data URIs;
            // https: covers customer-hosted images inside campaign previews.
            'img-src' => ["'self'", 'data:', 'https:'],
            'connect-src' => ["'self'"],
            'worker-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'object-src' => ["'none'"],
        ];

        if ($isLocal) {
            $directives['script-src'] = array_merge($directives['script-src'], self::DEV_ORIGINS);
            $directives['style-src'] = array_merge($directives['style-src'], self::DEV_ORIGINS);
            $directives['connect-src'] = array_merge(
                $directives['connect-src'],
                self::DEV_ORIGINS,
                ['ws://localhost:5173', 'ws://127.0.0.1:5173', 'ws://[::1]:5173'],
            );
        } else {
            // Only meaningful over HTTPS; emitting it locally would break the
            // http:// dev server.
            $directives['upgrade-insecure-requests'] = [];
        }

        return collect($directives)
            ->map(fn (array $values, string $directive) => trim($directive.' '.implode(' ', $values)))
            ->implode('; ').';';
    }
}
