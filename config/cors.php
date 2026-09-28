<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Published deliberately rather than relying on the framework default, which
| is `allowed_origins => ['*']` for every `api/*` path.
|
| Two very different surfaces live under /api:
|
|   1. /api/v1/leads/capture — an embeddable signup form posted from the
|      customer's own website, so it must accept any origin. It carries no
|      cookies and authenticates with a per-form public key, and the form's
|      own `allowed_origins` allow-list is enforced in the controller.
|
|   2. Everything else — authenticated with a Sanctum cookie or bearer token.
|      Credentialed requests are restricted to first-party origins; a wildcard
|      here would be a cross-site read of every workspace's data.
|
*/

$frontend = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', (string) env('APP_URL', ''))),
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $frontend,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
    ],

    'max_age' => 3600,

    // Cookies only travel to the origins listed above.
    'supports_credentials' => true,

];
