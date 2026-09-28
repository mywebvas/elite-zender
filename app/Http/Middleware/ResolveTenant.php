<?php

namespace App\Http\Middleware;

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

        return TenantContext::run($tenant, fn () => TenantCache::withNamespace(
            $tenant,
            fn () => $next($request),
        ));
    }
}
