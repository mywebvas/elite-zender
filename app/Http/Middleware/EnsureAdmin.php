<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the operator console.
 *
 * Runs on the `admin` guard, which has its own session cookie — a customer
 * session can never satisfy it, and an admin session never leaks into the
 * tenant-facing app.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        /** @var Admin|null $admin */
        $admin = auth('admin')->user();

        if ($admin === null) {
            return redirect()->guest(route('admin.login'));
        }

        // A deactivated operator must lose access immediately, not at the end
        // of their session.
        if (! $admin->is_active) {
            /** @var \Illuminate\Contracts\Auth\StatefulGuard $guard */
            $guard = auth('admin');
            $guard->logout();

            $request->session()->invalidate();

            return redirect()->route('admin.login')->withErrors('This account has been deactivated.');
        }

        abort_unless($role === null || $admin->hasRoleAtLeast($role), 403);

        return $next($request);
    }
}
