<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects all non-HTTPS traffic to HTTPS in production.
 * No-op in local/testing so dev servers work without TLS.
 *
 * Works alongside HSTS header from SecurityHeaders middleware —
 * after the first HTTPS visit, browsers enforce it at client level.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure() && app()->isProduction()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
