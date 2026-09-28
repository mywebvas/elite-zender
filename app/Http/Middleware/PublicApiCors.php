<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wide-open, credential-free CORS for the embeddable lead-capture endpoint.
 *
 * The global CORS policy (config/cors.php) is intentionally restricted to
 * first-party origins because it supports credentials. This endpoint is the
 * one exception: it is posted from arbitrary customer websites, never carries
 * cookies, and authorises through the form's own public key plus its
 * `allowed_origins` list.
 */
class PublicApiCors
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->decorate($request, response('', 204));
        }

        return $this->decorate($request, $next($request));
    }

    private function decorate(Request $request, Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin', '*'));
        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type, Origin');
        $response->headers->set('Access-Control-Max-Age', '3600');
        // Explicitly false: a browser must never attach the visitor's cookies
        // to a cross-origin capture request.
        $response->headers->set('Access-Control-Allow-Credentials', 'false');
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}
