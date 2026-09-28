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

    public static function cacheKey(string $importId): string
    {
        return "contact-import:{$importId}";
    }

    public function handle(ContactCsvImporter $importer): void
    {
        $tenant = Tenant::find($this->tenantId);
        TenantContext::set($tenant);

        $disk = Storage::disk('local');

        try {
            $result = $importer->import($disk->path($this->storedPath), $this->tenantId, $this->listId);

            Cache::put(self::cacheKey($this->importId), [
                'state' => 'completed',
            ] + $result, now()->addHour());
        } catch (Throwable $e) {
            Log::error('Contact CSV import failed', [
                'import_id' => $this->importId,
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);

            Cache::put(self::cacheKey($this->importId), [
                'state' => 'failed',
                'message' => $e->getMessage(),
            ], now()->addHour());

            // A malformed file is a permanent, user-caused failure: surface it
            // in the status payload and stop. Only unexpected faults are
            // rethrown so the queue can retry / record them.
            if (! $e instanceof RuntimeException) {
                throw $e;
            }
        } finally {
            $disk->delete($this->storedPath);
            TenantContext::set(null);
        }
    }
}
