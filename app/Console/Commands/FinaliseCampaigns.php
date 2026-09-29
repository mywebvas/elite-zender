<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;

/**
 * Flips campaigns from `sending` to `completed` once every recipient has been
 * accounted for. Without this a finished campaign sits on "Sending" forever,
 * which is the single most common support ticket for a broadcast tool.
 *
 * "Accounted for" is the whole point, and the previous version got it wrong:
 * it required `sent_count >= recipients_count`, so one refused address — a
 * relay rejection, or an unsubscribe landing between fan-out and delivery —
 * left the campaign permanently mid-flight. A recipient is resolved when it
 * has been sent, has failed, or was deliberately skipped.
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
            ->whereRaw('sent_count + failed_count + skipped_count >= recipients_count')
            ->update([
                'status' => Campaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

        $this->components->info("Finalised {$completed} campaign(s).");

        return self::SUCCESS;
    }
}
