<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops authenticated HTML from being written to the browser's disk cache.
 *
 * Without this, a contact list or campaign the user viewed stays readable via
 * the back button — or in the on-disk cache — after they log out, which is the
 * classic finding on any shared or kiosk machine. Static assets are untouched
 * so the CDN and service worker keep working.
 */
class NoStoreForAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user()) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        if (str_contains($contentType, 'text/html') || str_contains($contentType, 'application/json')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
