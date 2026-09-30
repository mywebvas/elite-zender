<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\ContactCsvImporter;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Imports an uploaded CSV off the request cycle.
 *
 * Progress/outcome is published to the cache under a per-import key so the UI
 * can poll it without another table.
 */
class ImportContactsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public string $importId,
        public string $tenantId,
        public string $storedPath,
        public ?string $listId = null,
        public ?string $userId = null,
    ) {
        // Bulk work: never let a 500k-row import starve a live send.
        $this->onQueue('low');
    }

    /**
     * Status key for one import.
     *
     * The tenant is part of the key, not an assumption. The previous key was
     * `contact-import:{uuid}` and the polling endpoint authorised only
     * "may you view contacts?", so isolation rested entirely on the cache
     * store being prefixed per tenant — which `TenantCache` can only do for
     * stores that implement `setPrefix()`. On the array and file stores it
     * silently does nothing, and one workspace could read another's import
     * result by guessing an id. Bind the two together in the key itself.
     */
    public static function cacheKey(string $importId, string $tenantId): string
    {
        return "contact-import:{$tenantId}:{$importId}";
    }

    public function handle(ContactCsvImporter $importer): void
    {
        $tenant = Tenant::find($this->tenantId);
        $disk = Storage::disk('local');

        // `run()`, never `set()`/`set(null)`: the manual pair clears the
        // context instead of restoring it, so a job dispatched inside a
        // request (or run on the sync driver) unbinds the caller's tenant.
        TenantContext::run($tenant, function () use ($importer, $disk): void {
            try {
                $result = $importer->import($disk->path($this->storedPath), $this->tenantId, $this->listId);

                Cache::put(self::cacheKey($this->importId, $this->tenantId), [
                    'state' => 'completed',
                ] + $result, now()->addHour());
            } catch (Throwable $e) {
                Log::error('Contact CSV import failed', [
                    'import_id' => $this->importId,
                    'tenant_id' => $this->tenantId,
                    'user_id' => $this->userId,
                    'error' => $e->getMessage(),
                ]);

                Cache::put(self::cacheKey($this->importId, $this->tenantId), [
                    'state' => 'failed',
                    'message' => $e->getMessage(),
                ], now()->addHour());

                // A malformed file is a permanent, user-caused failure: surface
                // it in the status payload and stop. Only unexpected faults are
                // rethrown so the queue can retry / record them.
                if (! $e instanceof RuntimeException) {
                    throw $e;
                }
            } finally {
                $disk->delete($this->storedPath);
            }
        });
    }
}
