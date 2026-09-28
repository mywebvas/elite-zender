<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Per-tenant cache namespacing (docs/README.md decision #6).
 *
 * The previous implementation called `Redis::setPrefix(...)` inside a
 * `try { } catch (\Throwable) {}`. That method does not exist on Laravel's
 * Redis manager, so it threw `BadMethodCallException` on *every* request and
 * the catch swallowed it — the documented isolation control was a silent
 * no-op, and two tenants happily shared cache keys.
 *
 * Here the prefix is applied to the cache store itself (every first-party
 * store implements `setPrefix`) and restored afterwards, so a suspended or
 * exception-aborted request cannot leave another tenant's namespace bound on
 * a long-lived Octane worker.
 */
final class TenantCache
{
    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withNamespace(?Tenant $tenant, callable $callback): mixed
    {
        $store = Cache::store()->getStore();

        if ($tenant === null || ! method_exists($store, 'setPrefix') || ! method_exists($store, 'getPrefix')) {
            return $callback();
        }

        $previous = $store->getPrefix();

        $store->setPrefix(rtrim($previous, ':').':'.$tenant->keyPrefix());

        try {
            return $callback();
        } finally {
            $store->setPrefix($previous);
        }
    }
}
