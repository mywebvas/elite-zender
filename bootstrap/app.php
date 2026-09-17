<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global: security headers + HTTPS enforcement on every response
        $middleware->prepend([
            \App\Http\Middleware\ForceHttps::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Web group: resolve tenant context from authenticated user
        $middleware->web(append: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);

        // Rate-limiter aliases — Redis-backed in production, standard otherwise
        // (app()->isProduction() not available yet in withMiddleware; use env() instead)
        if (env('APP_ENV') === 'production') {
            $middleware->throttleWithRedis();
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
