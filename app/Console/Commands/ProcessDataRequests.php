<?php

namespace App\Console\Commands;

use App\Models\DataRequest;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Carries out erasure requests whose cooling-off window has closed, and
 * shreds exports that have aged out.
 *
 * Two properties this command has to hold, both of them the kind of thing
 * you only get one chance at:
 *
 *  - **It must not delete anything it was not asked to.** Every deletion is
 *    re-read and re-checked at execution time; a request that was cancelled
 *    in the meantime is skipped, and the tenant id comes from the request
 *    row rather than from anything ambient.
 *  - **Suppression survives.** `suppression_entries` hold one-way hashes and
 *    no addresses, and they exist because somebody asked never to be
 *    emailed again. That promise was made to *them*, not to the workspace,
 *    so erasing the workspace must not quietly re-enable mail to people who
 *    opted out. This is the documented reason the table stores hashes.
 */
class ProcessDataRequests extends Command
{
    protected $signature = 'elitesender:process-data-requests
                            {--dry-run : Report what would happen without deleting anything}';

    protected $description = 'Execute due workspace deletions and purge expired exports';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->components->twoColumnDetail('Workspaces deleted', (string) $this->deleteDue($dryRun));
        $this->components->twoColumnDetail('Exports purged', (string) $this->purgeExports($dryRun));

        return self::SUCCESS;
    }

    private function deleteDue(bool $dryRun): int
    {
        $count = 0;

        DataRequest::withoutGlobalScopes()
            ->where('type', DataRequest::TYPE_DELETION)
            ->where('status', DataRequest::STATUS_PENDING)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->chunkById(25, function ($requests) use ($dryRun, &$count): void {
                foreach ($requests as $request) {
                    if ($dryRun) {
                        $this->line("  would delete workspace <fg=red>{$request->tenant_id}</>");
                        $count++;

                        continue;
                    }

                    if ($this->eraseWorkspace($request)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function eraseWorkspace(DataRequest $request): bool
    {
        // Re-read under the transaction: the window exists precisely so it
        // can be cancelled, and it may have been cancelled a second ago.
        try {
            return DB::transaction(function () use ($request): bool {
                $fresh = DataRequest::withoutGlobalScopes()
                    ->whereKey($request->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($fresh === null || $fresh->status !== DataRequest::STATUS_PENDING) {
                    return false;
                }

                $tenant = Tenant::find($fresh->tenant_id);

                if ($tenant === null) {
                    $fresh->forceFill([
                        'status' => DataRequest::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ])->save();

                    return false;
                }

                // Detach the suppression list before the cascade reaches it.
                // These rows are hashes with no addresses in them, and they
                // exist so that people who unsubscribed stay unsubscribed —
                // a promise made to the recipient, not to the workspace.
                DB::table('suppression_entries')
                    ->where('tenant_id', $tenant->getKey())
                    ->update(['tenant_id' => null]);

                // Mark the request done first: the row is about to be
                // cascaded away with its tenant, and the audit trail of the
                // deletion is the one thing worth keeping.
                $fresh->forceFill([
                    'status' => DataRequest::STATUS_COMPLETED,
                    'completed_at' => now(),
                ])->save();

                Log::warning('Workspace permanently deleted on request', [
                    'tenant_id' => $tenant->getKey(),
                    'tenant_name' => $tenant->name,
                    'requested_by' => $fresh->requested_by,
                    'reason' => $fresh->reason,
                ]);

                $this->purgeFiles($tenant);

                // forceDelete, not delete: an erasure request that leaves a
                // soft-deleted row behind has not erased anything.
                $tenant->forceDelete();

                return true;
            });
        } catch (Throwable $e) {
            Log::error('Workspace deletion failed', [
                'data_request' => $request->getKey(),
                'tenant_id' => $request->tenant_id,
                'error' => $e->getMessage(),
            ]);

            $request->forceFill(['status' => DataRequest::STATUS_FAILED])->save();

            return false;
        }
    }

    /** Uploaded CSVs, payment proofs and any archives this workspace left behind. */
    private function purgeFiles(Tenant $tenant): void
    {
        $disk = Storage::disk('local');

        foreach (["exports/{$tenant->getKey()}", "imports/{$tenant->getKey()}", "payment-proofs/{$tenant->getKey()}"] as $directory) {
            // deleteDirectory is a no-op on a path that is not there, so the
            // existence check would only be decoration.
            $disk->deleteDirectory($directory);
        }
    }

    /**
     * An export is a zip of somebody's entire contact list. It should not
     * outlive the reason it was created.
     */
    private function purgeExports(bool $dryRun): int
    {
        $count = 0;

        DataRequest::withoutGlobalScopes()
            ->where('type', DataRequest::TYPE_EXPORT)
            ->whereNotNull('file_path')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($requests) use ($dryRun, &$count): void {
                foreach ($requests as $request) {
                    $count++;

                    if ($dryRun) {
                        continue;
                    }

                    Storage::disk('local')->delete((string) $request->file_path);

                    $request->forceFill([
                        'file_path' => null,
                        'file_size' => null,
                        'status' => DataRequest::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ])->save();
                }
            });

        return $count;
    }
}
