<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * N+1 and mass-assignment accidents crash in dev, not prod.
     * See docs/09-CODING-STANDARDS.md §4.
     *
     * Rate limiters (docs/05-API-CONTRACT.md §3):
     *   login — 5 attempts / min / IP (pre-auth)
     *   api   — plan-tiered sliding window per authenticated tenant
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        // Pre-auth: login / register / forgot-password — 5 attempts / min / IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by('global:rate:ip:' . $request->ip())
                ->response(fn () => response()->json([
                    'error' => [
                        'code'    => 'RATE_LIMITED',
                        'message' => 'Too many attempts. Please try again in a moment.',
                    ],
                ], 429));
        });

        // Authenticated API: plan-tiered limits (docs/05-API-CONTRACT.md §3)
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            // Resolve requests-per-minute cap from tenant plan (stub until billing lands)
            $rpm = match ($user?->tenant?->plan ?? 'starter') {
                'free'       => 30,
                'starter'    => 60,
                'growth'     => 180,
                'scale'      => 360,
                'enterprise' => 1000,
                default      => 60,
            };

            $key = $user
                ? 't:' . substr((string) $user->tenant_id, 0, 8) . ':api'
                : 'global:rate:ip:' . $request->ip();

            return Limit::perMinute($rpm)
                ->by($key)
                ->response(fn () => response()->json([
                    'error' => [
                        'code'    => 'RATE_LIMITED',
                        'message' => 'API rate limit exceeded. Upgrade your plan for higher limits.',
                    ],
                ], 429));
        });
    }
}
