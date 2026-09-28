<?php

use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// RoadRunner/Octane workers are started without ext-pcntl in some images;
// Laravel's signal handling references these constants unconditionally.
foreach (['SIGINT' => 2, 'SIGTERM' => 15, 'SIGHUP' => 1] as $signal => $value) {
    if (! defined($signal)) {
        define($signal, $value);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global: security headers + HTTPS enforcement on every response.
        $middleware->prepend([
            ForceHttps::class,
            SecurityHeaders::class,
        ]);

        // Tenant context must be bound for BOTH stacks. It used to be web-only,
        // which meant every `auth:sanctum` API request ran with no tenant
        // bound — and therefore with the HasTenant global scope inert.
        $middleware->web(append: [ResolveTenant::class]);
        $middleware->api(append: [ResolveTenant::class]);

        // RFC 8058 one-click unsubscribe is a cross-origin POST issued by the
        // mail client with no session and no CSRF token. The route is signed,
        // which is the integrity guarantee CSRF would otherwise provide.
        $middleware->validateCsrfTokens(except: [
            'unsubscribe/*',
        ]);

        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB);

        // NOTE: Redis-backed throttling is switched on in AppServiceProvider,
        // not here. This closure runs before the config repository exists, so
        // the previous `env('APP_ENV') === 'production'` check returned null
        // under `php artisan config:cache` (which skips loading .env) and
        // silently left production on the non-atomic throttler.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Never leak internals to the client; the full trace goes to the log.
        $exceptions->dontReport([
            Illuminate\Auth\AuthenticationException::class,
            Illuminate\Validation\ValidationException::class,
        ]);

        $exceptions->context(fn () => array_filter([
            'tenant_id' => App\Tenancy\TenantContext::id(),
            'user_id' => auth()->id(),
        ]));
    })->create();
