<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Current request tenant — bound by middleware, consumed by scopes/queue jobs.
 */
final class TenantContext
{
    private static ?Tenant $tenant = null;

    public static function set(?Tenant $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function id(): ?string
    {
        return self::$tenant?->id;
    }

    public static function tenant(): ?Tenant
    {
        return self::$tenant;
    }

    public static function short(): ?string
    {
        return self::$tenant ? substr(self::$tenant->id, 0, 8) : null;
    }

    /** Tenant context for a user (used by queue workers where no request exists). */
    public static function bindForUser(Authenticatable $user): void
    {
        self::$tenant = $user instanceof \App\Models\User ? $user->tenant : null;
    }
}
