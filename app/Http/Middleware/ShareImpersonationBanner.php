<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes impersonation impossible to forget.
 *
 * An operator who does not realise they are inside a customer's account will
 * eventually send a real campaign from it. The banner is not decoration; it is
 * the control that stops that.
 */
class ShareImpersonationBanner
{
    public function handle(Request $request, Closure $next): Response
    {
        $impersonationId = $request->session()->get('impersonation.id');

        View::share('impersonating', $impersonationId === null ? null : [
            'id' => $impersonationId,
            'tenant' => Tenant::find($request->session()->get('impersonation.tenant_id'))?->name,
        ]);

        return $next($request);
    }
}
