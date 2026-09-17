<?php

namespace App\Tenancy;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Drop this trait into any Eloquent model to get automatic audit logging.
 *
 * Hooks fire on: created, updated, deleted (including soft-deletes).
 *
 * Usage:
 *   use App\Tenancy\Auditable;
 *   class Campaign extends Model { use Auditable; }
 *
 * Excluded fields (never logged — they carry secrets or noise):
 *   password, remember_token, updated_at, created_at
 *
 * Key mutations that must be audited (docs/06-SECURITY-COMPLIANCE.md §1):
 *   - campaign send / status change / delete
 *   - smtp_account create / update / delete
 *   - user (registration) create / role change / delete
 *   - contact delete (Contact model wires this in M3)
 */
trait Auditable
{
    /** Fields whose values must never appear in audit logs. */
    protected array $auditExclude = [
        'password',
        'remember_token',
        'updated_at',
        'created_at',
    ];

    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            $model->writeAuditLog('created', [], $model->getAttributes());
        });

        static::updated(function (self $model): void {
            $model->writeAuditLog(
                'updated',
                $model->getOriginal(),
                $model->getChanges()
            );
        });

        static::deleted(function (self $model): void {
            // Distinguish soft-delete from hard-delete
            $event = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? 'force_deleted'
                : 'deleted';

            $model->writeAuditLog($event, $model->getOriginal(), []);
        });
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    protected function writeAuditLog(string $event, array $old, array $new): void
    {
        AuditLog::create([
            'tenant_id'      => $this->tenant_id ?? null,
            'user_id'        => Auth::id(),
            'event'          => $event,
            'auditable_type' => static::class,
            'auditable_id'   => $this->getKey(),
            'old_values'     => $this->filterAuditValues($old),
            'new_values'     => $this->filterAuditValues($new),
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
        ]);
    }

    protected function filterAuditValues(array $values): array
    {
        return array_diff_key($values, array_flip($this->auditExclude));
    }
}
