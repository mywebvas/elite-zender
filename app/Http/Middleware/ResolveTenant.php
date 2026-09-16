<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's tenant for the request: Eloquent global
 * scopes + Redis key prefix. Queue jobs use TenantContext::bindForUser().
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->tenant_id !== null) {
            $tenant = Tenant::find($user->tenant_id);
            TenantContext::set($tenant);

            if ($tenant !== null) {
                Redis::setPrefix('t:' . substr($tenant->id, 0, 8) . ':');
            }
        }

        return $next($request);
    }
}
