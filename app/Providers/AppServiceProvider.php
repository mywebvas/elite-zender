<?php

namespace App\Providers;

use App\Models\Tenant;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped, not singleton: the plan memo inside must die with the
        // request (or the queued job), which is exactly what `scoped` means
        // under Octane and inside a long-running queue worker.
        $this->app->scoped(\App\Billing\PlanGate::class);
    }

    /**
     * Bootstrap any application services.
     *
     * Rate limiters (docs/05-API-CONTRACT.md §3):
     *   login            — registered in FortifyServiceProvider (email+IP)
     *   api              — plan-tiered sliding window per authenticated tenant
     *   tracking         — open/click/unsubscribe relays hit by mail clients
     *   csv-import       — expensive bulk endpoint
     *   campaign-dispatch— guards against send storms
     */
    public function boot(): void
    {
        $this->applyPlatformSettings();
        $this->configureModels();
        $this->configureDates();
        $this->configurePasswords();
        $this->configureUrls();
        $this->configureThrottling();
        $this->registerRateLimiters();
    }

    /**
     * Push operator-managed settings over the compiled config.
     *
     * Wrapped defensively: during `migrate` on a fresh database the settings
     * table does not exist yet, and an installation must never be unable to
     * boot far enough to create it.
     */
    private function applyPlatformSettings(): void
    {
        if ($this->app->runningInConsole() && ! $this->app->environment('testing')) {
            // Migrations and key:generate run before the table can exist.
            try {
                app(\App\Platform\Settings::class)->apply();
            } catch (Throwable) {
                // Intentionally silent: no settings simply means config wins.
            }

            return;
        }

        try {
            app(\App\Platform\Settings::class)->apply();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Strictness policy.
     *
     * Lazy loading is a latent N+1: fatal locally and in CI, but only reported
     * in production — an unnoticed extra query beats a 500 in a customer's
     * face. Silently discarding a non-fillable attribute, by contrast, is
     * always a bug worth failing on: it means data the user submitted was
     * quietly dropped.
     */
    private function configureModels(): void
    {
        $strict = ! $this->isProduction();

        Model::shouldBeStrict($strict);
        Model::preventSilentlyDiscardingAttributes(true);
        Model::unguard(false);

        if (! $strict) {
            Model::preventLazyLoading();
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                Log::warning('Lazy loading violation', [
                    'model' => $model::class,
                    'relation' => $relation,
                ]);
            });
        }
    }

    private function configureDates(): void
    {
        Date::use(\Illuminate\Support\Carbon::class);
    }

    /** One password policy for registration, reset and confirmation. */
    private function configurePasswords(): void
    {
        Password::defaults(fn () => $this->isProduction()
            ? Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));
    }

    private function configureUrls(): void
    {
        // Signed unsubscribe links and password resets must not silently
        // downgrade to http:// behind a TLS-terminating proxy.
        if ($this->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Use the Redis-backed throttler whenever Redis is the cache backend.
     *
     * This lives here rather than in bootstrap/app.php because that closure
     * runs before the config repository is built.
     */
    private function isProduction(): bool
    {
        return config('app.env') === 'production';
    }

    private function configureThrottling(): void
    {
        if (config('cache.default') === 'redis') {
            $this->app->make('router')->aliasMiddleware(
                'throttle',
                \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            );
        }
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $tenant = $user?->tenant;

            $rpm = match ($this->planFor($tenant)) {
                'free' => 30,
                'starter' => 60,
                'growth' => 180,
                'scale' => 360,
                'enterprise' => 1000,
                default => 60,
            };

            $key = $user
                ? 't:'.substr((string) $user->tenant_id, 0, 8).':api'
                : 'global:rate:ip:'.$request->ip();

            return Limit::perMinute($rpm)->by($key)->response($this->tooManyRequests(
                'API rate limit exceeded. Upgrade your plan for higher limits.',
            ));
        });

        // Mail clients can fetch a pixel many times (previews, forwards), and
        // a whole office shares one egress IP — so this is generous on purpose
        // while still capping a scripted flood.
        RateLimiter::for('tracking', fn (Request $request) => Limit::perMinute(120)
            ->by('track:'.$request->ip())
            ->response($this->tooManyRequests('Too many requests.')));

        RateLimiter::for('csv-import', fn (Request $request) => Limit::perMinute(10)
            ->by('import:'.($request->user() ? $request->user()->tenant_id : $request->ip()))
            ->response($this->tooManyRequests('Too many imports. Please wait a moment.')));

        RateLimiter::for('campaign-dispatch', fn (Request $request) => Limit::perMinute(30)
            ->by('dispatch:'.($request->user() ? $request->user()->tenant_id : $request->ip()))
            ->response($this->tooManyRequests('Too many send requests. Please wait a moment.')));
    }

    /**
     * Billing plan for a tenant, used to tier the API rate limit.
     *
     * This used to read `settings.plan` from the tenant's JSON blob — a key
     * that nothing in the application has ever written. Every workspace
     * therefore fell through to the `starter` default: the free tier got
     * triple the requests it was sold, Scale customers got a sixth of theirs,
     * and the 429 body cheerfully told them to "upgrade for higher limits"
     * that upgrading could not deliver. The subscription is the only source
     * of truth for what someone bought.
     */
    private function planFor(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return 'free';
        }

        try {
            return app(\App\Billing\PlanGate::class)->planCodeFor($tenant);
        } catch (Throwable) {
            // A rate limiter must never be the reason a request 500s.
            return 'free';
        }
    }

    private function tooManyRequests(string $message): Closure
    {
        return fn (Request $request) => $request->expectsJson() || $request->is('api/*')
            ? response()->json(['error' => ['code' => 'RATE_LIMITED', 'message' => $message]], 429)
            : response()->view('errors.429', [], 429);
    }
}
