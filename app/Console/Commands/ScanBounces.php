<?php

namespace App\Console\Commands;

use App\Jobs\CheckImapBouncesJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ScanBounces extends Command
{
    protected $signature = 'elitesender:scan-bounces';

    protected $description = 'Queue a bounce-mailbox scan for every workspace that has IMAP configured';

    public function handle(): int
    {
        $queued = 0;

        Tenant::query()
            ->where('status', Tenant::STATUS_ACTIVE)
            ->chunkById(200, function ($tenants) use (&$queued): void {
                foreach ($tenants as $tenant) {
                    if (! is_array($tenant->setting('imap'))) {
                        continue;
                    }

                    CheckImapBouncesJob::dispatch((string) $tenant->getKey());
                    $queued++;
                }
            });

        $this->components->info("Queued {$queued} bounce scan(s).");

        return self::SUCCESS;
    }
}
