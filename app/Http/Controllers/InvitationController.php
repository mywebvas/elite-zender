<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\Team\TeamMemberJoined;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Accepting an invitation.
 *
 * Public by necessity — the invitee has no account yet — so the token is the
 * entire boundary. It is compared as a SHA-256 against a stored hash, it
 * expires, and accepting it consumes it.
 *
 * The invited address is *not* editable at accept time. Letting the invitee
 * choose their own address would turn one leaked link into a way into any
 * workspace under any identity; and the address is already proven, because
 * the link only ever reached that inbox — which is why an invited member
 * starts verified and can send immediately.
 */
class InvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $invitation = $this->resolve($token);

        if ($invitation === null) {
            return view('team.invitation-invalid');
        }

        // Already signed in? Only the invited address may accept, because
        // a user belongs to exactly one workspace.
        if (Auth::check()) {
            return view('team.invitation-conflict', [
                'invitation' => $invitation,
                'current' => Auth::user(),
            ]);
        }

        return view('team.invitation-accept', [
            'invitation' => $invitation,
            'token' => $token,
            'workspace' => $invitation->tenant?->name,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->resolve($token);

        if ($invitation === null) {
            return redirect()->route('login')
                ->withErrors('That invitation link is no longer valid. Ask for a fresh one.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
            // The address is fixed by the invitation; this only guards
            // against a stale form being replayed against a different one.
            'email' => ['nullable', 'email', Rule::in([$invitation->email])],
        ]);

        $user = DB::transaction(function () use ($invitation, $validated): User {
            $user = User::create([
                'tenant_id' => $invitation->tenant_id,
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => Hash::make($validated['password']),
                'role' => $invitation->role,
            ]);

            // The invitation link proves control of the inbox, which is
            // exactly what verification proves — so an invited colleague is
            // productive immediately instead of waiting on a second email.
            $user->forceFill(['email_verified_at' => now()])->save();

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        // Tell whoever invited them that the seat is now filled.
        $inviter = $invitation->inviter;

        if ($inviter !== null) {
            Notification::send($inviter, new TeamMemberJoined($user));
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('onboarding')
            ->with('success', 'Welcome aboard — you now have access to '.$this->workspaceName($invitation).'.');
    }

    /** The workspace's display name, defensively — a tenant can be deleted. */
    private function workspaceName(Invitation $invitation): string
    {
        $tenant = $invitation->tenant;

        return $tenant instanceof \App\Models\Tenant ? (string) $tenant->name : 'the workspace';
    }

    /** A live, unexpired, unaccepted invitation for this token — or null. */
    private function resolve(string $token): ?Invitation
    {
        return Invitation::withoutGlobalScopes()
            ->with(['tenant', 'inviter'])
            ->where('token_hash', Invitation::hashToken($token))
            ->pending()
            ->first();
    }
}
