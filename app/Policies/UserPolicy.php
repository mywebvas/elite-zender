<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Who may change the shape of a workspace's team.
 *
 * Named for the model it guards so Laravel discovers it automatically.
 *
 * Three rules beyond ordinary RBAC, each of which prevents a state somebody
 * has to open a support ticket to escape:
 *
 *  - Nobody may remove or demote themselves. An owner who demotes their own
 *    account locks the workspace out of its own billing.
 *  - Only an owner may create or remove another owner. An admin promoting
 *    themselves to owner is privilege escalation with extra steps.
 *  - The last owner cannot be removed or demoted, ever.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasRoleAtLeast(Role::ADMIN);
    }

    public function invite(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** May $user assign this role? Only an owner may mint another owner. */
    public function assignRole(User $user, string $role): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $role !== Role::OWNER || $user->isOwner();
    }

    public function update(User $user, User $member): bool
    {
        return $this->canActOn($user, $member)
            // Only an owner may change another owner's role.
            && (! $member->isOwner() || $user->isOwner());
    }

    public function remove(User $user, User $member): bool
    {
        return $this->update($user, $member);
    }

    private function canActOn(User $user, User $member): bool
    {
        return $this->viewAny($user)
            && $user->tenant_id === $member->tenant_id
            // Self-service demotion and self-removal are how an owner locks
            // their own workspace out of its billing.
            && $user->getKey() !== $member->getKey();
    }
}
