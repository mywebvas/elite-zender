<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * UUID v7 primary keys: time-ordered, non-enumerable, k-sortable.
 * Laravel's built-in HasUuids generates UUID v4 by default; this pins v7.
 */
trait HasUuid7
{
    public static function bootHasUuid7(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getKeyName() && $model->getKey() === null) {
                $model->setAttribute($model->getKeyName(), \Illuminate\Support\Str::uuid7()->toString());
            }
        });
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public function getIncrementing(): bool
    {
        return false;
    }
}
