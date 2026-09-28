<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared authorization rules for every tenant-owned resource.
 *
 * Two orthogonal questions are answered here:
 *
 *  1. **Isolation** — does the record belong to the acting user's tenant?
 *     The `HasTenant` global scope already hides other tenants' rows from
 *     queries, but policies are the belt to that scope's braces: anything
 *     resolved with `withoutGlobalScopes()` (jobs, tracking, webhooks) still
 *     has to pass through here.
 *
 *  2. **RBAC** — viewers read, members write, admins/owners administer.
 */
abstract class TenantResourcePolicy
{
    /** Role required to create/update/delete this resource type. */
    protected string $writeRole = Role::MEMBER;

    /** Role required to permanently remove this resource type. */
    protected string $deleteRole = Role::MEMBER;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasRoleAtLeast($this->writeRole);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model) && $user->hasRoleAtLeast($this->writeRole);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model) && $user->hasRoleAtLeast($this->deleteRole);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->delete($user, $model);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model) && $user->hasRoleAtLeast(Role::ADMIN);
    }

    protected function sameTenant(User $user, Model $model): bool
    {
        $tenantId = $user->tenant_id ?? TenantContext::id();

        return $tenantId !== null && $model->getAttribute('tenant_id') === $tenantId;
    }
}
