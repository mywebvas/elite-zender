<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use App\Models\Role;
use App\Models\User;
use App\Platform\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Cross-tenant customer users.
 *
 * Support's first question is almost always "I have an email address, which
 * workspace is it in?" — previously unanswerable without database access.
 */
class UserController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        return view('admin.users.index', [
            'users' => User::withoutGlobalScopes()
                ->with('tenant:id,name,status')
                ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search): void {
                    $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                    $inner->where('email', 'like', $term)->orWhere('name', 'like', $term);
                }))
                ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
                ->latest()
                ->paginate(40)
                ->withQueryString(),
            'search' => $search,
            'roles' => Role::all(),
        ]);
    }

    /**
     * Force a password reset and end every existing session.
     *
     * The two belong together: resetting a password while the attacker's
     * session cookie stays valid accomplishes nothing.
     */
    public function resetPassword(Request $request, string $id): RedirectResponse
    {
        $user = User::withoutGlobalScopes()->findOrFail($id);

        $temporary = Str::password(18);

        $user->forceFill([
            'password' => Hash::make($temporary),
            'remember_token' => Str::random(60),
        ])->save();

        $this->invalidateSessions($user);

        $this->activity->record(
            action: 'user.reset_password',
            description: "Reset the password for {$user->email} and signed out every session",
            subject: $user,
            tenantId: $user->tenant_id,
            severity: AdminActivity::SEVERITY_CRITICAL,
            reason: $request->string('reason')->toString() ?: null,
        );

        return back()
            ->with('new_user_password', $temporary)
            ->with('success', 'Password reset and all sessions ended. Share the temporary password securely.');
    }

    /** End every session for a user without changing their password. */
    public function signOut(Request $request, string $id): RedirectResponse
    {
        $user = User::withoutGlobalScopes()->findOrFail($id);

        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $this->invalidateSessions($user);

        $this->activity->record(
            action: 'user.sign_out',
            description: "Signed out every session for {$user->email}",
            subject: $user,
            tenantId: $user->tenant_id,
            severity: AdminActivity::SEVERITY_NOTICE,
            reason: $request->string('reason')->toString() ?: null,
        );

        return back()->with('success', 'All sessions ended.');
    }

    /** Change a customer user's role, e.g. to restore a locked-out owner. */
    public function updateRole(Request $request, string $id): RedirectResponse
    {
        $user = User::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'role' => ['required', Rule::in(Role::all())],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $before = $user->role;
        $user->forceFill(['role' => $validated['role']])->save();

        $this->activity->record(
            action: 'user.update_role',
            description: "Changed {$user->email} from {$before} to {$validated['role']}",
            subject: $user,
            tenantId: $user->tenant_id,
            severity: AdminActivity::SEVERITY_CRITICAL,
            reason: $validated['reason'] ?? null,
            changes: ['from' => $before, 'to' => $validated['role']],
        );

        return back()->with('success', 'Role updated.');
    }

    /**
     * Drop server-side sessions for a user.
     *
     * Only possible with the database session driver; with cookie or array
     * sessions the rotated remember-token is the best available lever, which
     * is why that is always done as well.
     */
    private function invalidateSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        try {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
