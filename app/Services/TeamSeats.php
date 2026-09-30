<?php

namespace App\Services;

use App\Billing\PlanGate;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;

/**
 * Seat accounting for a workspace.
 *
 * The subtlety that matters: a pending invitation is a *committed* seat. If
 * only accepted members counted, a Starter workspace with three seats could
 * send thirty invitations and quietly end up with thirty members, because
 * each acceptance is checked against a limit that was already satisfied when
 * the invitation went out. Counting invitations against the limit is the
 * only way the number on the pricing page means anything.
 */
final class TeamSeats
{
    public function __construct(
        private readonly PlanGate $planGate,
    ) {}

    /** Seats already spoken for: members plus live invitations. */
    public function used(Tenant $tenant): int
    {
        return $this->members($tenant) + $this->pendingInvitations($tenant);
    }

    public function members(Tenant $tenant): int
    {
        return User::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->count();
    }

    public function pendingInvitations(Tenant $tenant): int
    {
        return Invitation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->pending()
            ->count();
    }

    /** The plan's seat ceiling, or null when unlimited. */
    public function limit(Tenant $tenant): ?int
    {
        return $this->planGate->limitFor($tenant, 'users');
    }

    /** Seats still available, or null when unlimited. */
    public function remaining(Tenant $tenant): ?int
    {
        $limit = $this->limit($tenant);

        return $limit === null ? null : max(0, $limit - $this->used($tenant));
    }

    public function canInvite(Tenant $tenant): bool
    {
        $remaining = $this->remaining($tenant);

        return $remaining === null || $remaining > 0;
    }

    /**
     * Why an invitation is refused, in words a customer can act on — or null
     * when it is allowed.
     */
    public function invitationBlockReason(Tenant $tenant): ?string
    {
        if ($this->canInvite($tenant)) {
            return null;
        }

        $limit = (int) $this->limit($tenant);

        return sprintf(
            'Your plan includes %d team %s and %s already in use (including pending invitations). Upgrade to add more.',
            $limit,
            $limit === 1 ? 'seat' : 'seats',
            $this->used($tenant) === 1 ? 'one is' : $this->used($tenant).' are',
        );
    }
}
