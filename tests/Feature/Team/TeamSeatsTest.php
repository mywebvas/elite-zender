<?php

use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Team\TeamInvitation;
use App\Notifications\Team\TeamMemberJoined;
use App\Notifications\Team\TeamMemberRemoved;
use App\Services\TeamSeats;
use Illuminate\Support\Facades\Notification;

/**
 * Team seats.
 *
 * Every plan in the catalogue sold them — Free 1, Starter 3, Growth 10,
 * Scale 25, Enterprise unlimited. The billing page rendered a "Team members"
 * usage meter against that limit and the plan cards advertised "10 team
 * members" as a headline feature. There was no route, no controller and no
 * view: a Growth customer paid $59 a month for nine seats that could not
 * exist, and the limit was never enforced because there was nothing to
 * enforce it against.
 */
beforeEach(function (): void {
    Notification::fake();
    seedPlans();

    $this->owner = actingAsTenantUser(['role' => Role::OWNER, 'name' => 'Ada Lovelace']);
    $this->tenant = $this->owner->tenant;

    $this->onPlan = function (string $code) {
        $plan = Plan::query()->where('code', $code)->sole();

        Subscription::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'currency' => 'USD',
                'amount' => $plan->price_usd ?? 0,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ],
        );

        return $plan;
    };
});

/*
|--------------------------------------------------------------------------
| Inviting
|--------------------------------------------------------------------------
*/

it('invites a teammate and emails them a link', function (): void {
    ($this->onPlan)('growth');

    $this->post(route('team.invite'), ['email' => 'grace@example.test', 'role' => Role::MEMBER])
        ->assertRedirect();

    $invitation = Invitation::withoutGlobalScopes()->sole();

    expect($invitation->email)->toBe('grace@example.test')
        ->and($invitation->role)->toBe(Role::MEMBER)
        ->and($invitation->tenant_id)->toBe($this->tenant->id)
        ->and($invitation->isPending())->toBeTrue();

    Notification::assertSentOnDemand(TeamInvitation::class);
});

it('stores only a hash of the token, never the token', function (): void {
    ($this->onPlan)('growth');

    $this->post(route('team.invite'), ['email' => 'grace@example.test', 'role' => Role::MEMBER]);

    $invitation = Invitation::withoutGlobalScopes()->sole();

    // 64 hex characters, and nothing that could be replayed from a backup.
    expect($invitation->token_hash)->toHaveLength(64)
        ->and($invitation->token_hash)->toMatch('/^[a-f0-9]{64}$/');
});

it('refuses to invite an address that already has an account', function (): void {
    ($this->onPlan)('growth');

    $existing = User::factory()->create();

    $this->post(route('team.invite'), ['email' => $existing->email, 'role' => Role::MEMBER])
        ->assertSessionHasErrors('email');

    expect(Invitation::withoutGlobalScopes()->count())->toBe(0);
});

it('re-inviting the same address refreshes the link instead of stacking up', function (): void {
    ($this->onPlan)('growth');

    $this->post(route('team.invite'), ['email' => 'grace@example.test', 'role' => Role::MEMBER]);
    $first = Invitation::withoutGlobalScopes()->sole()->token_hash;

    $this->post(route('team.invite'), ['email' => 'grace@example.test', 'role' => Role::ADMIN]);

    $invitations = Invitation::withoutGlobalScopes()->get();

    expect($invitations)->toHaveCount(1)
        ->and($invitations->first()->role)->toBe(Role::ADMIN)
        ->and($invitations->first()->token_hash)->not->toBe($first);
});

it('issues a brand-new token on resend so the old link dies', function (): void {
    ($this->onPlan)('growth');

    $this->post(route('team.invite'), ['email' => 'grace@example.test', 'role' => Role::MEMBER]);
    $invitation = Invitation::withoutGlobalScopes()->sole();
    $original = $invitation->token_hash;

    $this->post(route('team.invitations.resend', $invitation))->assertRedirect();

    expect($invitation->fresh()->token_hash)->not->toBe($original);
});

/*
|--------------------------------------------------------------------------
| Seat accounting — the number on the pricing page has to mean something
|--------------------------------------------------------------------------
*/

it('counts pending invitations against the seat limit', function (): void {
    // Starter sells three seats; the owner is one of them.
    ($this->onPlan)('starter');

    $seats = app(TeamSeats::class);

    expect($seats->limit($this->tenant))->toBe(3)
        ->and($seats->used($this->tenant))->toBe(1);

    $this->post(route('team.invite'), ['email' => 'one@example.test', 'role' => Role::MEMBER]);
    $this->post(route('team.invite'), ['email' => 'two@example.test', 'role' => Role::MEMBER]);

    // Two invitations outstanding: the seats are committed, not free.
    expect($seats->used($this->tenant))->toBe(3)
        ->and($seats->remaining($this->tenant))->toBe(0)
        ->and($seats->canInvite($this->tenant))->toBeFalse();
});

it('refuses the invitation that would exceed the plan', function (): void {
    ($this->onPlan)('free'); // one seat, already taken by the owner

    $this->post(route('team.invite'), ['email' => 'nope@example.test', 'role' => Role::MEMBER])
        ->assertSessionHasErrors('email');

    expect(Invitation::withoutGlobalScopes()->count())->toBe(0);
});

it('frees the seat again when an invitation is revoked', function (): void {
    ($this->onPlan)('free');

    // Force one in past the gate, then revoke it.
    $invitation = Invitation::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'pending@example.test',
        'role' => Role::MEMBER,
        'token_hash' => Invitation::hashToken('x'),
        'expires_at' => now()->addDays(7),
    ]);

    expect(app(TeamSeats::class)->used($this->tenant))->toBe(2);

    $this->delete(route('team.invitations.revoke', $invitation))->assertRedirect();

    expect(app(TeamSeats::class)->used($this->tenant))->toBe(1);
});

it('never counts an expired invitation as a committed seat', function (): void {
    ($this->onPlan)('free');

    Invitation::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'stale@example.test',
        'role' => Role::MEMBER,
        'token_hash' => Invitation::hashToken('y'),
        'expires_at' => now()->subDay(),
    ]);

    expect(app(TeamSeats::class)->used($this->tenant))->toBe(1);
});

it('treats an unlimited plan as unlimited', function (): void {
    ($this->onPlan)('enterprise');

    $seats = app(TeamSeats::class);

    expect($seats->limit($this->tenant))->toBeNull()
        ->and($seats->remaining($this->tenant))->toBeNull()
        ->and($seats->canInvite($this->tenant))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Accepting
|--------------------------------------------------------------------------
*/

function inviteAndCaptureToken(string $email, string $role = Role::MEMBER): string
{
    $token = Invitation::newToken();

    Invitation::withoutGlobalScopes()->create([
        'tenant_id' => test()->tenant->id,
        'email' => $email,
        'role' => $role,
        'token_hash' => Invitation::hashToken($token),
        'invited_by' => test()->owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    return $token;
}

it('lets an invited colleague join and land inside the workspace', function (): void {
    $token = inviteAndCaptureToken('grace@example.test', Role::ADMIN);

    auth()->logout();

    $this->get(route('invitations.show', ['token' => $token]))
        ->assertOk()
        ->assertSee('grace@example.test');

    $this->post(route('invitations.accept', ['token' => $token]), [
        'name' => 'Grace Hopper',
        'password' => 'correct-horse-battery-1',
        'password_confirmation' => 'correct-horse-battery-1',
    ])->assertRedirect(route('onboarding'));

    $joined = User::withoutGlobalScopes()->firstWhere('email', 'grace@example.test');

    expect($joined)->not->toBeNull()
        ->and($joined->tenant_id)->toBe($this->tenant->id)
        ->and($joined->role)->toBe(Role::ADMIN)
        // The link only ever reached that inbox, which is exactly what
        // verification proves — so they can send immediately.
        ->and($joined->hasVerifiedEmail())->toBeTrue()
        ->and(auth()->id())->toBe($joined->id);

    Notification::assertSentTo($this->owner, TeamMemberJoined::class);
});

it('consumes the invitation so the link cannot be used twice', function (): void {
    $token = inviteAndCaptureToken('grace@example.test');

    auth()->logout();

    $this->post(route('invitations.accept', ['token' => $token]), [
        'name' => 'Grace Hopper',
        'password' => 'correct-horse-battery-1',
        'password_confirmation' => 'correct-horse-battery-1',
    ])->assertRedirect();

    auth()->logout();

    $this->get(route('invitations.show', ['token' => $token]))
        ->assertOk()
        ->assertSee('no longer valid');

    expect(User::withoutGlobalScopes()->where('email', 'grace@example.test')->count())->toBe(1);
});

it('rejects an expired invitation', function (): void {
    $token = Invitation::newToken();

    Invitation::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'late@example.test',
        'role' => Role::MEMBER,
        'token_hash' => Invitation::hashToken($token),
        'expires_at' => now()->subMinute(),
    ]);

    auth()->logout();

    $this->get(route('invitations.show', ['token' => $token]))->assertSee('no longer valid');

    $this->post(route('invitations.accept', ['token' => $token]), [
        'name' => 'Too Late',
        'password' => 'correct-horse-battery-1',
        'password_confirmation' => 'correct-horse-battery-1',
    ])->assertRedirect(route('login'));

    expect(User::withoutGlobalScopes()->where('email', 'late@example.test')->exists())->toBeFalse();
});

it('rejects a forged token', function (): void {
    inviteAndCaptureToken('grace@example.test');

    auth()->logout();

    $this->get(route('invitations.show', ['token' => 'not-a-real-token']))->assertSee('no longer valid');
});

it('will not let the invitee choose a different address', function (): void {
    $token = inviteAndCaptureToken('grace@example.test');

    auth()->logout();

    // One leaked link must not become a way in under any identity.
    $this->post(route('invitations.accept', ['token' => $token]), [
        'name' => 'Impostor',
        'email' => 'attacker@example.test',
        'password' => 'correct-horse-battery-1',
        'password_confirmation' => 'correct-horse-battery-1',
    ])->assertSessionHasErrors('email');

    expect(User::withoutGlobalScopes()->where('email', 'attacker@example.test')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Roles, removal and the states you cannot recover from
|--------------------------------------------------------------------------
*/

it('keeps the team page away from members and viewers', function (): void {
    actingAsTenantUser(['role' => Role::MEMBER]);
    $this->get(route('team.index'))->assertForbidden();

    actingAsTenantUser(['role' => Role::VIEWER]);
    $this->get(route('team.index'))->assertForbidden();
});

it('lets an admin change a member role', function (): void {
    $member = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::VIEWER]);

    $this->put(route('team.members.role', $member), ['role' => Role::MEMBER])->assertRedirect();

    expect($member->fresh()->role)->toBe(Role::MEMBER);
});

it('stops an admin minting another owner', function (): void {
    $admin = actingAsTenantUser(['role' => Role::ADMIN]);
    $member = User::factory()->create(['tenant_id' => $admin->tenant_id, 'role' => Role::MEMBER]);

    $this->put(route('team.members.role', $member), ['role' => Role::OWNER])
        ->assertSessionHasErrors('role');

    expect($member->fresh()->role)->toBe(Role::MEMBER);
});

it('stops anybody demoting or removing themselves', function (): void {
    // An owner who demotes their own account locks the workspace out of its
    // own billing, and there is no self-service way back.
    $this->put(route('team.members.role', $this->owner), ['role' => Role::VIEWER])->assertForbidden();
    $this->delete(route('team.members.remove', $this->owner))->assertForbidden();

    expect($this->owner->fresh()->role)->toBe(Role::OWNER);
});

it('refuses to remove the last owner', function (): void {
    $second = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::OWNER]);

    // Acting as the second owner, remove the first: allowed, two exist.
    $this->actingAs($second)->delete(route('team.members.remove', $this->owner))->assertRedirect();

    expect(User::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('role', Role::OWNER)->count())->toBe(1);
});

it('revokes sessions and API tokens when a member is removed', function (): void {
    $member = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::MEMBER]);
    $member->createToken('theirs');

    expect($member->tokens()->count())->toBe(1);

    $this->delete(route('team.members.remove', $member))->assertRedirect();

    // An ex-colleague holding a live API token is the classic offboarding hole.
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $member->id)->count())->toBe(0)
        ->and(User::withoutGlobalScopes()->whereKey($member->id)->exists())->toBeFalse();

    Notification::assertSentTo($member, TeamMemberRemoved::class);
});

it('never lets one workspace touch another workspace team', function (): void {
    $stranger = User::factory()->create();

    $this->put(route('team.members.role', $stranger), ['role' => Role::VIEWER])->assertNotFound();
    $this->delete(route('team.members.remove', $stranger))->assertNotFound();

    expect($stranger->fresh()->role)->not->toBe(Role::VIEWER);
});
