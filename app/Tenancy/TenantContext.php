<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Current request tenant — bound by middleware, consumed by scopes/queue jobs.
 */
final class TenantContext
{
    public static function set(?Tenant $tenant): void
    {
        if ($tenant) {
            app()->instance('current_tenant', $tenant);
        } else {
            app()->forgetInstance('current_tenant');
        }
    }

    public static function id(): ?string
    {
        return self::tenant()?->id;
    }

    public static function tenant(): ?Tenant
    {
        return app()->bound('current_tenant') ? app('current_tenant') : null;
    }

    public static function short(): ?string
    {
        $tenant = self::tenant();
        return $tenant ? substr($tenant->id, 0, 8) : null;
    }

    /** Tenant context for a user (used by queue workers where no request exists). */
    public static function bindForUser(Authenticatable $user): void
    {
        self::set($user instanceof \App\Models\User ? $user->tenant : null);
    }
}
