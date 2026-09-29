<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Team\TeamInvitation;
use App\Notifications\Team\TeamMemberRemoved;
use App\Services\TeamSeats;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Workspace team management.
 *
 * Every plan sells seats and the billing page has always rendered a "Team
 * members" meter against that limit — there was simply no way to fill one.
 * A Growth customer paid for ten and could have one.
 *
 * Seats are counted as members *plus live invitations*, because otherwise a
 * three-seat workspace can issue thirty invitations that each pass the check
 * at the moment they are sent.
 */
class TeamController extends Controller
{
    public function __construct(
        private readonly TeamSeats $seats,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $tenant = $this->tenant();

        return view('team.index', [
            'members' => User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->orderByRaw(sprintf(
                    "case role when '%s' then 0 when '%s' then 1 when '%s' then 2 else 3 end",
                    Role::OWNER, Role::ADMIN, Role::MEMBER,
                ))
                ->orderBy('name')
                ->get(),
            'invitations' => Invitation::withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->whereNull('accepted_at')
                ->latest()
                ->get(),
            'seats' => [
                'used' => $this->seats->used($tenant),
                'limit' => $this->seats->limit($tenant),
                'remaining' => $this->seats->remaining($tenant),
            ],
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $this->authorize('invite', User::class);

        $tenant = $this->tenant();

        $validated = $request->validate([
            'email' => [
                'required', 'email:rfc', 'max:255',
                // A person belongs to exactly one workspace in this schema,
                // so an address that already has an account cannot be invited
                // into a second one. Saying so plainly beats a unique-index
                // violation at accept time.
                Rule::unique('users', 'email'),
            ],
            'role' => ['required', 'string', Rule::in(array_keys($this->assignableRoles()))],
        ], [
            'email.unique' => 'That address already belongs to an account. Ask them to sign in, or invite a different address.',
        ]);

        if (! $this->user()->can('assignRole', [User::class, $validated['role']])) {
            return back()->withErrors(['role' => 'Only an owner can invite another owner.'])->withInput();
        }

        if ($reason = $this->seats->invitationBlockReason($tenant)) {
            return back()->withErrors(['email' => $reason])->withInput();
        }

        $token = Invitation::newToken();

        // updateOrCreate, so re-inviting the same address refreshes the link
        // and the clock instead of colliding with the unique index.
        $invitation = Invitation::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getKey(), 'email' => mb_strtolower($validated['email'])],
            [
                'role' => $validated['role'],
                'token_hash' => Invitation::hashToken($token),
                'invited_by' => $this->user()->getKey(),
                'expires_at' => now()->addDays(Invitation::TTL_DAYS),
                'accepted_at' => null,
            ],
        );

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitation($invitation, $tenant, $this->user(), $token));

        return back()->with('success', "Invitation sent to {$invitation->email}.");
    }

    public function resend(string $id): RedirectResponse
    {
        $this->authorize('invite', User::class);

        $invitation = $this->findInvitation($id);

        abort_if($invitation->accepted_at !== null, 404);

        // A fresh token, always: resending must invalidate whatever was in
        // the previous email, or revoking is meaningless.
        $token = Invitation::newToken();

        $invitation->forceFill([
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => now()->addDays(Invitation::TTL_DAYS),
        ])->save();

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitation($invitation, $this->tenant(), $this->user(), $token));

        return back()->with('success', "Invitation resent to {$invitation->email}.");
    }

    public function revoke(string $id): RedirectResponse
    {
        $this->authorize('invite', User::class);

        $invitation = $this->findInvitation($id);
        $email = $invitation->email;

        $invitation->delete();

        return back()->with('success', "Invitation to {$email} revoked.");
    }

    public function updateRole(Request $request, string $id): RedirectResponse
    {
        $member = $this->findMember($id);

        $this->authorize('update', $member);

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(array_keys($this->assignableRoles()))],
        ]);

        if (! $this->user()->can('assignRole', [User::class, $validated['role']])) {
            return back()->withErrors(['role' => 'Only an owner can grant ownership.']);
        }

        if ($this->wouldRemoveLastOwner($member, $validated['role'])) {
            return back()->withErrors(['role' => 'A workspace needs at least one owner. Promote somebody else first.']);
        }

        $member->forceFill(['role' => $validated['role']])->save();

        return back()->with('success', "{$member->name} is now a ".$this->assignableRoles()[$validated['role']].'.');
    }

    public function remove(string $id): RedirectResponse
    {
        $member = $this->findMember($id);

        $this->authorize('remove', $member);

        if ($this->wouldRemoveLastOwner($member, null)) {
            return back()->withErrors(['member' => 'A workspace needs at least one owner. Promote somebody else first.']);
        }

        $name = $member->name;

        // Sessions and API tokens die with the seat: an ex-colleague with a
        // live token is the classic offboarding hole.
        $member->tokens()->delete();
        $member->forceFill(['remember_token' => null])->save();

        $member->notify(new TeamMemberRemoved($this->tenant()));

        $member->delete();

        return back()->with('success', "{$name} no longer has access.");
    }

    /**
     * Roles a workspace admin may hand out.
     *
     * @return array<string, string>
     */
    private function assignableRoles(): array
    {
        return [
            Role::OWNER => 'Owner',
            Role::ADMIN => 'Admin',
            Role::MEMBER => 'Member',
            Role::VIEWER => 'Viewer',
        ];
    }

    /**
     * Would this change leave the workspace with nobody who can pay the bill?
     *
     * @param  string|null  $newRole  null when the member is being removed
     */
    private function wouldRemoveLastOwner(User $member, ?string $newRole): bool
    {
        if (! $member->isOwner() || $newRole === Role::OWNER) {
            return false;
        }

        return User::withoutGlobalScopes()
            ->where('tenant_id', $member->tenant_id)
            ->where('role', Role::OWNER)
            ->count() <= 1;
    }

    private function findMember(string $id): User
    {
        return User::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant()->getKey())
            ->whereKey($id)
            ->firstOrFail();
    }

    private function findInvitation(string $id): Invitation
    {
        return Invitation::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant()->getKey())
            ->whereKey($id)
            ->firstOrFail();
    }

    private function user(): User
    {
        /** @var User|null $user */
        $user = auth('web')->user();

        abort_if($user === null, 403);

        return $user;
    }

    private function tenant(): \App\Models\Tenant
    {
        $tenant = TenantContext::tenant();

        abort_if($tenant === null, 403);

        return $tenant;
    }
}
