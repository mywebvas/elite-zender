<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminImpersonation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * "Log in as this customer."
 *
 * The most dangerous capability in the platform, so it is never silent:
 *   - every session is written to admin_impersonations before it starts,
 *   - the admin guard stays authenticated underneath, so returning is one
 *     click and cannot strand the operator,
 *   - the customer-facing UI shows a persistent banner throughout.
 *
 * The admin session is intentionally NOT logged out. Logging out and back in
 * would mean storing an admin id in the tenant session to get back — a
 * privilege-escalation primitive if that session is ever fixated.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, string $userId): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        abort_unless($admin->canImpersonate(), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::withoutGlobalScopes()->findOrFail($userId);

        abort_if($user->tenant_id === null, 422, 'That user has no workspace.');

        $record = AdminImpersonation::create([
            'admin_id' => $admin->getKey(),
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->getKey(),
            'reason' => $validated['reason'] ?? null,
            'ip_address' => $request->ip(),
            'started_at' => now(),
        ]);

        Log::warning('Admin started impersonation', [
            'admin_id' => $admin->getKey(),
            'user_id' => $user->getKey(),
            'tenant_id' => $user->tenant_id,
            'reason' => $validated['reason'] ?? null,
        ]);

        /** @var \Illuminate\Contracts\Auth\StatefulGuard $web */
        $web = Auth::guard('web');
        $web->login($user);

        $request->session()->put('impersonation.id', $record->getKey());
        $request->session()->put('impersonation.tenant_id', $user->tenant_id);

        return redirect()->route('dashboard')
            ->with('warning', "You are viewing {$user->name}'s workspace as an operator.");
    }

    public function stop(Request $request): RedirectResponse
    {
        $id = $request->session()->pull('impersonation.id');
        $request->session()->forget('impersonation.tenant_id');

        if ($id !== null) {
            AdminImpersonation::whereKey($id)->update(['ended_at' => now()]);

            Log::info('Admin ended impersonation', ['impersonation_id' => $id]);
        }

        /** @var \Illuminate\Contracts\Auth\StatefulGuard $web */
        $web = Auth::guard('web');
        $web->logout();

        // The admin guard was never logged out, so the operator lands straight
        // back in the console.
        return redirect()->route('admin.dashboard')->with('success', 'Impersonation ended.');
    }
}
