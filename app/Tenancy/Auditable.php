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
 * Excluded fields (never logged — secrets, PII or pure noise) come from
 * `$auditExclude`; models may extend the list. Values are additionally
 * truncated so a 1 MB campaign body never lands in the audit table.
 *
 * Bulk paths (CSV import, seeding, backfills) should wrap themselves in
 * {@see self::withoutAuditing()}: writing one audit row per imported contact
 * doubles the write volume of an import for no forensic value.
 */
trait Auditable
{
    private static bool $auditingDisabled = false;

    /**
     * Fields whose values must never appear in audit logs.
     *
     * @var list<string>
     */
    protected array $auditExclude = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'updated_at',
        'created_at',
    ];

    /** Longest value retained per attribute in the audit payload. */
    protected int $auditValueLimit = 500;

    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            $model->writeAuditLog('created', [], $model->getAttributes());
        });

        static::updated(function (self $model): void {
            $model->writeAuditLog('updated', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function (self $model): void {
            $event = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? 'force_deleted'
                : 'deleted';

            $model->writeAuditLog($event, $model->getOriginal(), []);
        });
    }

    /**
     * Run a callback with audit logging suppressed.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = self::$auditingDisabled;
        self::$auditingDisabled = true;

        try {
            return $callback();
        } finally {
            self::$auditingDisabled = $previous;
        }
    }

    public static function auditingIsDisabled(): bool
    {
        return self::$auditingDisabled;
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function writeAuditLog(string $event, array $old, array $new): void
    {
        if (self::$auditingDisabled) {
            return;
        }

        AuditLog::create([
            'tenant_id' => $this->getAttribute('tenant_id'),
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $this->filterAuditValues($old),
            'new_values' => $this->filterAuditValues($new),
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function filterAuditValues(array $values): array
    {
        $filtered = array_diff_key($values, array_flip($this->auditExclude));

        return array_map(function ($value) {
            if (is_string($value) && mb_strlen($value) > $this->auditValueLimit) {
                return mb_substr($value, 0, $this->auditValueLimit).'…[truncated]';
            }

            return $value;
        }, $filtered);
    }
}
