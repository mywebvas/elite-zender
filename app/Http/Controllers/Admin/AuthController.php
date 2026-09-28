<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show(): View|RedirectResponse
    {
        return auth('admin')->check()
            ? redirect()->route('admin.dashboard')
            : view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string'],
        ]);

        // Tighter than the customer limiter: this login guards every workspace
        // on the platform, not one.
        $key = 'admin-login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        /** @var \Illuminate\Contracts\Auth\StatefulGuard $guard */
        $guard = Auth::guard('admin');

        // The is_active constraint is part of the credential query, so a
        // deactivated operator is never even password-checked.
        if (! $guard->attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 900);

            Log::warning('Failed admin login', ['email' => $credentials['email'], 'ip' => $request->ip()]);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        /** @var \App\Models\Admin|null $admin */
        $admin = $guard->user();
        $admin?->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        Log::info('Admin signed in', ['admin_id' => $admin?->getKey(), 'ip' => $request->ip()]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var \Illuminate\Contracts\Auth\StatefulGuard $guard */
        $guard = Auth::guard('admin');
        $guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
