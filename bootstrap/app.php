<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureRegistrationIsOpen;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\NoStoreForAuthenticated;
use App\Http\Middleware\PublicApiCors;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ShareImpersonationBanner;
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
        then: function (): void {
            // The operator console is a separate surface on the web stack:
            // same session middleware, different guard.
            Illuminate\Support\Facades\Route::middleware('web')
                ->group(__DIR__.'/../routes/admin.php');
        },
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
        $middleware->web(append: [
            // Fortify owns the registration routes, so the operator's
            // "Allow new signups" switch has to be enforced from the stack
            // rather than bolted onto a route definition we do not control.
            EnsureRegistrationIsOpen::class,
            ResolveTenant::class,
            ShareImpersonationBanner::class,
            NoStoreForAuthenticated::class,
        ]);
        $middleware->api(append: [ResolveTenant::class, NoStoreForAuthenticated::class]);

        $middleware->alias([
            'public-cors' => PublicApiCors::class,
            'admin' => EnsureAdmin::class,
        ]);

        // RFC 8058 one-click unsubscribe is a cross-origin POST issued by the
        // mail client with no session and no CSRF token. The route is signed,
        // which is the integrity guarantee CSRF would otherwise provide.
        $middleware->validateCsrfTokens(except: [
            'unsubscribe/*',
            // Payment providers cannot hold a CSRF token; each webhook is
            // authenticated by its own HMAC signature instead.
            'webhooks/billing/*',
        ]);

        /*
         * Trusted proxies.
         *
         * `at: '*'` means "believe X-Forwarded-For from anybody", which lets a
         * client forge its own source IP — defeating every IP-keyed rate limit
         * and poisoning the audit trail. Railway and most PaaS front ends sit
         * on an unpredictable internal IP, so '*' is the pragmatic default
         * there, but it MUST be narrowed with TRUSTED_PROXIES on any
         * deployment where the load-balancer range is known.
         */
        $middleware->trustProxies(
            at: array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '*'))))),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

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
