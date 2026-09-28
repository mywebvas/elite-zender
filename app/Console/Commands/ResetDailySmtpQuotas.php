<?php

namespace App\Console\Commands;

use App\Models\SmtpAccount;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Resets `smtp_accounts.sent_today` at each workspace's local midnight.
 *
 * Daily caps are a deliverability control, so "daily" has to mean the
 * customer's day, not the server's. The command runs hourly and only touches
 * workspaces whose local clock just rolled past midnight.
 */
class ResetDailySmtpQuotas extends Command
{
    protected $signature = 'elitesender:reset-smtp-quotas {--force : Reset every workspace regardless of local time}';

    protected $description = 'Reset per-relay daily send counters at each tenant\'s local midnight';

    public function handle(): int
    {
        $reset = 0;

        Tenant::query()->chunkById(200, function ($tenants) use (&$reset): void {
            foreach ($tenants as $tenant) {
                if (! $this->option('force') && ! $this->isLocalMidnightHour($tenant)) {
                    continue;
                }

                $reset += SmtpAccount::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->getKey())
                    ->where('sent_today', '>', 0)
                    ->update(['sent_today' => 0]);
            }
        });

        $this->components->info("Reset daily quota on {$reset} relay(s).");

        return self::SUCCESS;
    }

    private function isLocalMidnightHour(Tenant $tenant): bool
    {
        try {
            return Carbon::now($tenant->timezone())->hour === 0;
        } catch (Throwable) {
            // A corrupt timezone setting must not stall the whole sweep.
            return Carbon::now('UTC')->hour === 0;
        }
    }
}
