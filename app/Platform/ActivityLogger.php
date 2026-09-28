<?php

namespace App\Platform;

use App\Models\Admin;
use App\Models\AdminActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records operator actions to a queryable table.
 *
 * Writing to the log file was never enough: nobody greps a production log to
 * answer "who changed this price, when, and what did they say the reason was?"
 * — and log files rotate away long before an audit does.
 *
 * Failures here are swallowed on purpose. An operator suspending an abusive
 * workspace must not be blocked because the audit write hit a deadlock; the
 * failure is reported instead.
 */
final class ActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function record(
        string $action,
        string $description,
        ?Model $subject = null,
        ?string $tenantId = null,
        string $severity = AdminActivity::SEVERITY_INFO,
        ?string $reason = null,
        ?array $changes = null,
    ): ?AdminActivity {
        /** @var Admin|null $admin */
        $admin = auth('admin')->user();

        try {
            $activity = AdminActivity::create([
                'admin_id' => $admin?->getKey(),
                'admin_email' => $admin === null ? 'system' : $admin->email,
                'action' => $action,
                'description' => $description,
                'subject_type' => $subject === null ? null : $subject::class,
                'subject_id' => $subject?->getKey(),
                'tenant_id' => $tenantId,
                'severity' => $severity,
                'reason' => $reason,
                'changes' => $this->redact($changes),
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        // Critical actions also go to the application log, so they reach
        // whatever alerting the platform log shipper feeds.
        if ($severity === AdminActivity::SEVERITY_CRITICAL) {
            Log::warning("Admin action: {$action}", [
                'admin' => $admin?->email,
                'description' => $description,
                'tenant_id' => $tenantId,
                'reason' => $reason,
            ]);
        }

        return $activity;
    }

    /**
     * Strip anything that must never be written to an audit row.
     *
     * An audit trail that leaks the secret it was recording the rotation of
     * is worse than no audit trail.
     *
     * @param  array<string, mixed>|null  $changes
     * @return array<string, mixed>|null
     */
    private function redact(?array $changes): ?array
    {
        if ($changes === null) {
            return null;
        }

        $sensitive = ['password', 'secret', 'token', 'key', 'authorization', 'card'];

        foreach ($changes as $field => $value) {
            foreach ($sensitive as $needle) {
                if (str_contains(strtolower((string) $field), $needle)) {
                    $changes[$field] = '[redacted]';

                    continue 2;
                }
            }

            if (is_string($value) && mb_strlen($value) > 300) {
                $changes[$field] = mb_substr($value, 0, 300).'…';
            }
        }

        return $changes;
    }
}
