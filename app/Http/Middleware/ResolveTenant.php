<?php

namespace App\Http\Middleware;

use App\Billing\PlanGate;
use App\Models\Tenant;
use App\Tenancy\TenantCache;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's tenant for the request: Eloquent global
 * scopes + cache key namespace. Queue jobs use TenantContext::run().
 */
class ResolveTenant
{
    /**
     * Publish the workspace's billing state to every view.
     *
     * One resolution per request, shared — otherwise each page re-queries the
     * subscription to decide whether to show a banner, and they drift.
     */
    private function shareBillingState(?Tenant $tenant): void
    {
        if ($tenant === null) {
            \Illuminate\Support\Facades\View::share('billingNotice', null);

            return;
        }

        $planGate = app(PlanGate::class);
        $subscription = $planGate->subscriptionFor($tenant);

        \Illuminate\Support\Facades\View::share('billingNotice', match (true) {
            $subscription === null => null,

            $subscription->status === \App\Models\Subscription::STATUS_EXPIRED => [
                'tone' => 'danger',
                'message' => 'Your subscription has lapsed and sending is paused. Your data is untouched — settle the open invoice to pick up where you left off.',
            ],

            $subscription->status === \App\Models\Subscription::STATUS_PAST_DUE => [
                'tone' => 'warning',
                'message' => 'We could not collect your last payment. Sending continues for now; please settle the open invoice to avoid interruption.',
            ],

            $subscription->isEnding() => [
                'tone' => 'info',
                'message' => sprintf(
                    'Your plan ends on %s. You can undo this any time before then.',
                    $subscription->cancel_at?->toFormattedDayDateString() ?? 'the end of the period',
                ),
            ],

            $subscription->onTrial() && $subscription->trial_ends_at->diffInDays(now()) >= -3 => [
                'tone' => 'info',
                'message' => sprintf('Your trial ends %s. Add a payment method to keep everything running.', $subscription->trial_ends_at->diffForHumans()),
            ],

            default => null,
        });
    }

    public function handle(Request $request, Closure $next): Response
    {
        // The operator console is cross-tenant by design and must never be
        // scoped to (or blocked by) whatever customer session happens to be
        // open in the same browser.
        if ($request->is('admin', 'admin/*')) {
            return $next($request);
        }

        // Explicitly the `web` guard, never the ambient default. Admin routes
        // share this middleware stack, and an operator authenticated on the
        // `admin` guard has no tenant_id — asking the default guard for "the
        // user" would hand this an Admin and blow up mid-request.
        /** @var \App\Models\User|null $user */
        $user = auth('web')->user();
        $tenant = null;

        if ($user !== null && $user->tenant_id !== null) {
            $tenant = Tenant::find($user->tenant_id);

            if ($tenant === null) {
                // The account points at a workspace that no longer exists.
                // Continuing would run every query unscoped, so refuse.
                Log::warning('ResolveTenant: user references a missing tenant', [
                    'user_id' => $user->getKey(),
                    'tenant_id' => $user->tenant_id,
                ]);

                abort(403, 'Your workspace is unavailable.');
            }

            // A suspended workspace is closed to its own users, but an
            // operator must still be able to get in and out of it — otherwise
            // suspending a workspace while impersonating strands them there
            // with no route back to the console.
            if ($tenant->status !== Tenant::STATUS_ACTIVE && ! auth('admin')->check()) {
                abort(403, 'This workspace has been suspended.');
            }
        }

        return TenantContext::run($tenant, fn () => TenantCache::withNamespace($tenant, function () use ($next, $request, $tenant) {
            $this->shareBillingState($tenant);

            return $next($request);
        }));
    }
}
