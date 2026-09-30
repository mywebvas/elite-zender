<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honour the operator's "Allow new signups" switch.
 *
 * The switch existed in the admin console, was written to the settings table,
 * and was read by nothing at all — registration stayed wide open however it
 * was set. An operator reaching for it is usually mid-incident (an abuse
 * wave, a migration, an invite-only launch), which is the worst possible
 * moment to discover a control is decorative.
 *
 * Applied by route name rather than path so it keeps working if Fortify ever
 * moves its endpoints. Existing customers are untouched: this only guards the
 * two registration routes.
 */
class EnsureRegistrationIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('register', 'register.store')) {
            return $next($request);
        }

        if (config('platform.signups_open', true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => [
                    'code' => 'SIGNUPS_CLOSED',
                    'message' => 'New workspaces are not being created at the moment.',
                ],
            ], 403);
        }

        return response()->view('auth.signups-closed', [
            'supportEmail' => config('platform.support_email'),
        ], 403);
    }
}
