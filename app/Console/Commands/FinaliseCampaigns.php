<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;

/**
 * Flips campaigns from `sending` to `completed` once every recipient has been
 * attempted. Without this a finished campaign sits on "Sending" forever, which
 * is the single most common support ticket for a broadcast tool.
 */
class FinaliseCampaigns extends Command
{
    protected $signature = 'elitesender:finalise-campaigns';

    protected $description = 'Mark fully-delivered campaigns as completed';

    public function handle(): int
    {
        $completed = Campaign::withoutGlobalScopes()
            ->where('status', Campaign::STATUS_SENDING)
            ->where('recipients_count', '>', 0)
            ->whereColumn('sent_count', '>=', 'recipients_count')
            ->update([
                'status' => Campaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

        $this->components->info("Finalised {$completed} campaign(s).");

        return self::SUCCESS;
    }
}
