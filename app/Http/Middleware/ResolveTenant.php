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
        $user = $request->user();
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

            if ($tenant->status !== Tenant::STATUS_ACTIVE) {
                abort(403, 'This workspace has been suspended.');
            }
        }

        return TenantContext::run($tenant, fn () => TenantCache::withNamespace(
            $tenant,
            fn () => $next($request),
        ));
    }
}
