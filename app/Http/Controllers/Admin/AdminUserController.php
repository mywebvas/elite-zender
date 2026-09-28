<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminActivity;
use App\Platform\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Operator accounts.
 *
 * Previously creatable only over SSH with `artisan elitesender:make-admin`,
 * which meant onboarding a support hire required a deploy engineer and
 * offboarding one relied on somebody remembering.
 */
class AdminUserController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(): View
    {
        return view('admin.team.index', [
            'admins' => Admin::withTrashed()->withCount('impersonations')->orderBy('name')->get(),
            'roles' => [
                Admin::ROLE_SUPPORT => 'Support — read-only, can impersonate to reproduce a report',
                Admin::ROLE_ADMIN => 'Admin — can act on customer data and billing',
                Admin::ROLE_SUPER_ADMIN => 'Super admin — unrestricted, including pricing and refunds',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('admins', 'email')->withoutTrashed()],
            'role' => ['required', Rule::in(Admin::ROLES)],
        ]);

        // Generated, never chosen by the creator: a password typed into a form
        // by one person and read aloud to another is a shared credential.
        $temporary = Str::password(20);

        $admin = Admin::create([
            ...$validated,
            'password' => Hash::make($temporary),
            'is_active' => true,
        ]);

        $this->activity->record(
            action: 'admin.create',
            description: "Created operator {$admin->email} ({$admin->role})",
            subject: $admin,
            severity: AdminActivity::SEVERITY_CRITICAL,
        );

        return back()
            ->with('new_admin_password', $temporary)
            ->with('success', 'Operator created. Share the temporary password over a secure channel — it is shown only once.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $admin = Admin::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'role' => ['required', Rule::in(Admin::ROLES)],
        ]);

        // An operator cannot promote or demote themselves. Self-elevation is
        // the whole point of a privilege boundary.
        if ($admin->is(auth('admin')->user()) && $validated['role'] !== $admin->role) {
            return back()->withErrors('You cannot change your own role. Ask another super admin.');
        }

        $before = $admin->only(['name', 'role']);
        $admin->update($validated);

        if ($admin->wasChanged()) {
            $this->activity->record(
                action: 'admin.update',
                description: "Updated operator {$admin->email}",
                subject: $admin,
                severity: AdminActivity::SEVERITY_CRITICAL,
                changes: ['from' => $before, 'to' => $admin->only(['name', 'role'])],
            );
        }

        return back()->with('success', 'Operator updated.');
    }

    /** Deactivate or reinstate. Access ends on the next request, not at cookie expiry. */
    public function toggle(string $id): RedirectResponse
    {
        $admin = Admin::findOrFail($id);

        if ($admin->is(auth('admin')->user())) {
            return back()->withErrors('You cannot deactivate your own account.');
        }

        if ($admin->isSuperAdmin() && $admin->is_active && Admin::where('is_active', true)->where('role', Admin::ROLE_SUPER_ADMIN)->count() <= 1) {
            // Locking every super admin out of the platform is unrecoverable
            // without shell access.
            return back()->withErrors('This is the last active super admin. Promote someone else first.');
        }

        $admin->forceFill(['is_active' => ! $admin->is_active])->save();

        $this->activity->record(
            action: $admin->is_active ? 'admin.activate' : 'admin.deactivate',
            description: ($admin->is_active ? 'Reinstated' : 'Deactivated')." operator {$admin->email}",
            subject: $admin,
            severity: AdminActivity::SEVERITY_CRITICAL,
        );

        return back()->with('success', $admin->is_active ? 'Operator reinstated.' : 'Operator deactivated.');
    }

    /** Issue a fresh temporary password. */
    public function resetPassword(string $id): RedirectResponse
    {
        $admin = Admin::findOrFail($id);

        $temporary = Str::password(20);
        $admin->forceFill(['password' => Hash::make($temporary)])->save();

        $this->activity->record(
            action: 'admin.reset_password',
            description: "Reset the password for {$admin->email}",
            subject: $admin,
            severity: AdminActivity::SEVERITY_CRITICAL,
        );

        return back()
            ->with('new_admin_password', $temporary)
            ->with('success', 'Password reset. Share it over a secure channel.');
    }
}
