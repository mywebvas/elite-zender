<?php

namespace App\Console\Commands;

use App\Billing\BillingService;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The daily billing cycle: trials, renewals, dunning retries and lapses.
 *
 * Deliberately one command rather than four. These steps are ordered — a trial
 * that ends today should be invoiced today, and an invoice raised today should
 * not also be judged overdue today — and splitting them across schedules makes
 * that ordering accidental.
 */
class RunBillingCycle extends Command
{
    protected $signature = 'elitesender:billing-cycle {--dry-run : Report what would happen without changing anything}';

    protected $description = 'Expire trials, renew subscriptions, retry failed charges and lapse unpaid accounts';

    public function handle(BillingService $billing): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $report = [
            'trials ended' => $this->endTrials($billing, $dryRun),
            'renewals invoiced' => $this->renew($billing, $dryRun),
            'charges retried' => $this->retry($billing, $dryRun),
            'accounts lapsed' => $this->lapse($billing, $dryRun),
        ];

        foreach ($report as $label => $count) {
            $this->components->twoColumnDetail(ucfirst($label), (string) $count);
        }

        return self::SUCCESS;
    }

    /**
     * A trial that ends today converts: paid plans get an invoice, free plans
     * simply become active. Nobody is ever locked out at the moment a trial
     * ends — that is what the grace window is for.
     */
    private function endTrials(BillingService $billing, bool $dryRun): int
    {
        $count = 0;

        Subscription::withoutGlobalScopes()
            ->with(['plan', 'tenant'])
            ->where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->chunkById(100, function ($subscriptions) use ($billing, $dryRun, &$count): void {
                foreach ($subscriptions as $subscription) {
                    $count++;

                    if ($dryRun) {
                        continue;
                    }

                    $plan = $subscription->plan;

                    if ($plan === null || ($plan->priceFor($subscription->currency) ?? 0) === 0) {
                        $subscription->forceFill([
                            'status' => Subscription::STATUS_ACTIVE,
                            'current_period_start' => now(),
                            'current_period_end' => now()->addMonth(),
                        ])->save();

                        continue;
                    }

                    $invoice = $billing->invoiceRenewal($subscription);

                    if ($invoice !== null && ! $billing->attemptAutoCharge($subscription, $invoice)) {
                        // No card on file yet: the customer has until the
                        // invoice falls due to pay it.
                        $subscription->forceFill(['status' => Subscription::STATUS_PAST_DUE])->save();
                    }
                }
            });

        return $count;
    }

    /** Invoice every active subscription whose period has ended, then try to collect. */
    private function renew(BillingService $billing, bool $dryRun): int
    {
        $count = 0;

        Subscription::withoutGlobalScopes()
            ->with(['plan', 'pendingPlan'])
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->chunkById(100, function ($subscriptions) use ($billing, $dryRun, &$count): void {
                foreach ($subscriptions as $subscription) {
                    $count++;

                    if ($dryRun) {
                        continue;
                    }

                    // A scheduled cancellation takes effect here: the period the
                    // customer paid for has finished, so nothing further is owed.
                    if ($subscription->canceled_at !== null) {
                        $this->applyCancellation($billing, $subscription);

                        continue;
                    }

                    $plan = $subscription->pendingPlan ?? $subscription->plan;

                    if ($plan === null) {
                        continue;
                    }

                    $invoice = $billing->invoiceRenewal($subscription);

                    if ($invoice === null) {
                        continue; // free plan, already rolled over
                    }

                    if ($billing->attemptAutoCharge($subscription, $invoice)) {
                        $billing->rollPeriod($subscription, $plan, $invoice->total);

                        continue;
                    }

                    // Offline payers and missing cards both land here: the
                    // invoice is open, access continues through the grace window.
                    $subscription->forceFill(['status' => Subscription::STATUS_PAST_DUE])->save();
                }
            });

        return $count;
    }

    /** Re-attempt cards that declined, on the widening dunning schedule. */
    private function retry(BillingService $billing, bool $dryRun): int
    {
        $count = 0;

        Subscription::withoutGlobalScopes()
            ->with('plan')
            ->where('status', Subscription::STATUS_PAST_DUE)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->chunkById(100, function ($subscriptions) use ($billing, $dryRun, &$count): void {
                foreach ($subscriptions as $subscription) {
                    $invoice = Invoice::withoutGlobalScopes()
                        ->where('subscription_id', $subscription->getKey())
                        ->where('status', Invoice::STATUS_OPEN)
                        ->latest()
                        ->first();

                    if ($invoice === null) {
                        continue;
                    }

                    $count++;

                    if ($dryRun) {
                        continue;
                    }

                    if ($billing->attemptAutoCharge($subscription, $invoice)) {
                        $plan = Plan::find($invoice->plan_id) ?? $subscription->plan;

                        if ($plan !== null) {
                            $billing->rollPeriod($subscription, $plan, $invoice->total);
                        }
                    }
                }
            });

        return $count;
    }

    /**
     * Suspend accounts whose invoice has been open past the grace window.
     *
     * Access stops; nothing is deleted. A customer who pays a week late should
     * find their lists, campaigns and history exactly as they left them.
     */
    private function lapse(BillingService $billing, bool $dryRun): int
    {
        $count = 0;
        $cutoff = now()->subDays((int) config('billing.grace_days', 7));

        Subscription::withoutGlobalScopes()
            ->where('status', Subscription::STATUS_PAST_DUE)
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<', $cutoff);
            })
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $cutoff)
            ->chunkById(100, function ($subscriptions) use ($billing, $dryRun, &$count): void {
                foreach ($subscriptions as $subscription) {
                    $count++;

                    if (! $dryRun) {
                        $billing->lapse($subscription);
                    }
                }
            });

        return $count;
    }

    /** A cancelled subscription falls back to the free plan, keeping the data. */
    private function applyCancellation(BillingService $billing, Subscription $subscription): void
    {
        $free = Plan::query()->where('code', 'free')->first();

        if ($free === null) {
            $subscription->forceFill(['status' => Subscription::STATUS_CANCELED])->save();

            return;
        }

        $subscription->forceFill([
            'plan_id' => $free->getKey(),
            'pending_plan_id' => null,
            'status' => Subscription::STATUS_ACTIVE,
            'amount' => 0,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'canceled_at' => null,
            'cancel_at' => null,
            'gateway_token' => null,
        ])->save();

        Log::info('Subscription cancellation applied; workspace moved to the free plan', [
            'tenant_id' => $subscription->tenant_id,
        ]);
    }
}
