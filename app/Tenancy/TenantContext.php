<?php

namespace App\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Current request tenant — bound by middleware, consumed by scopes/queue jobs.
 *
 * Under Octane/RoadRunner the container survives between requests, so a
 * tenant left bound by an aborted request would leak into the next one. Always
 * prefer {@see self::run()} over a bare set()/set(null) pair: it restores the
 * previous tenant in a `finally`, which a manual reset cannot promise once an
 * exception or an early `return` enters the picture.
 */
final class TenantContext
{
    public const CONTAINER_KEY = 'current_tenant';

    public static function set(?Tenant $tenant): void
    {
        if ($tenant) {
            app()->instance(self::CONTAINER_KEY, $tenant);
        } else {
            app()->forgetInstance(self::CONTAINER_KEY);
        }
    }

    public static function id(): ?string
    {
        return self::tenant()?->getKey();
    }

    public static function tenant(): ?Tenant
    {
        return app()->bound(self::CONTAINER_KEY) ? app(self::CONTAINER_KEY) : null;
    }

    /** Short, human-scannable tenant discriminator for logs. */
    public static function short(): ?string
    {
        $tenant = self::tenant();

        return $tenant ? substr((string) $tenant->getKey(), 0, 8) : null;
    }

    /**
     * Run a callback inside a tenant, then restore whatever was bound before.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function run(?Tenant $tenant, callable $callback): mixed
    {
        $previous = self::tenant();

        self::set($tenant);

        try {
            return $callback();
        } finally {
            self::set($previous);
        }
    }

    /**
     * Run a callback with no tenant bound — i.e. across the whole estate.
     * Reserved for system jobs (bounce ingestion, retention sweeps).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function runWithoutTenant(callable $callback): mixed
    {
        return self::run(null, $callback);
    }

    /** Tenant context for a user (used by queue workers where no request exists). */
    public static function bindForUser(Authenticatable $user): void
    {
        self::set($user instanceof User ? $user->tenant : null);
    }
}
