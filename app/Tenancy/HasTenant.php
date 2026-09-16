<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Row-scoped multi-tenancy: global scope + auto-assign tenant_id on create.
 * Apply to every tenant-scoped model. Never query without it.
 */
trait HasTenant
{
    public static function bootHasTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (TenantContext::id() !== null) {
                $builder->where($builder->getModel()->qualifyColumn('tenant_id'), TenantContext::id());
            }
        });

        static::creating(function (Model $model): void {
            if (! $model->isDirty('tenant_id') && TenantContext::id() !== null) {
                $model->tenant_id = TenantContext::id();
            }
        });
    }
}
