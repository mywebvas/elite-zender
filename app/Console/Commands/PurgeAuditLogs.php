<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Enforces the 24-month audit retention window
 * (docs/06-SECURITY-COMPLIANCE.md). Deletes in bounded batches so the sweep
 * never holds a long transaction on a hot table.
 */
class PurgeAuditLogs extends Command
{
    protected $signature = 'elitesender:purge-audit-logs {--months=24 : Retention window in months} {--chunk=1000}';

    protected $description = 'Delete audit log entries older than the retention window';

    public function handle(): int
    {
        $cutoff = now()->subMonths(max(1, (int) $this->option('months')));
        $chunk = max(100, (int) $this->option('chunk'));
        $deleted = 0;

        do {
            $batch = AuditLog::query()
                ->where('created_at', '<', $cutoff)
                ->limit($chunk)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        $this->components->info("Purged {$deleted} audit log entr(ies) older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
