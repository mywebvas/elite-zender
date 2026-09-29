<?php

namespace App\Console\Commands;

use App\Lifecycle\LifecycleMessenger;
use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Tenant;
use App\Notifications\Lifecycle\CampaignReport;
use Illuminate\Console\Command;
use Throwable;

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
        // Collected before the update, because afterwards they no longer
        // match the predicate and there is nothing left to report on.
        $finalising = Campaign::withoutGlobalScopes()
            ->where('status', Campaign::STATUS_SENDING)
            ->where('recipients_count', '>', 0)
            ->whereRaw('sent_count + failed_count + skipped_count >= recipients_count')
            ->get();

        $completed = Campaign::withoutGlobalScopes()
            ->whereKey($finalising->modelKeys())
            ->update([
                'status' => Campaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

        foreach ($finalising as $campaign) {
            $this->report($campaign);
        }

        $this->components->info("Finalised {$completed} campaign(s).");

        return self::SUCCESS;
    }

    /**
     * Send the performance report.
     *
     * This is the retention email for a product whose whole value is
     * measurement: it arrives at the moment the customer cares most, proves
     * the thing they paid for worked, and pulls them back in on the strength
     * of their own numbers rather than a marketing prompt.
     *
     * Wrapped defensively — a reporting failure must never stop a campaign
     * being marked complete, which is the actual job of this command.
     */
    private function report(Campaign $campaign): void
    {
        try {
            $tenant = Tenant::find($campaign->tenant_id);

            if ($tenant === null) {
                return;
            }

            $counts = CampaignEvent::withoutGlobalScopes()
                ->where('campaign_id', $campaign->getKey())
                ->selectRaw(
                    'sum(case when type = ? then 1 else 0 end) as opens, sum(case when type = ? then 1 else 0 end) as clicks',
                    [CampaignEvent::TYPE_OPEN, CampaignEvent::TYPE_CLICK],
                )
                ->first();

            app(LifecycleMessenger::class)->sendOnce(
                $tenant,
                'campaign_report:'.$campaign->getKey(),
                fn () => new CampaignReport(
                    $campaign->fresh() ?? $campaign,
                    (int) ($counts->opens ?? 0),
                    (int) ($counts->clicks ?? 0),
                ),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
