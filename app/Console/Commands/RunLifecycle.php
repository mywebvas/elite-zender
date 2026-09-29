<?php

namespace App\Console\Commands;

use App\Billing\PlanGate;
use App\Lifecycle\LifecycleMessenger;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Lifecycle\ActivationNudge;
use App\Notifications\Lifecycle\CardExpiring;
use App\Notifications\Lifecycle\InvoiceReminder;
use App\Notifications\Lifecycle\RenewalReminder;
use App\Notifications\Lifecycle\SuspensionWarning;
use App\Notifications\Lifecycle\TrialEnding;
use App\Notifications\Lifecycle\UsageThresholdReached;
use App\Notifications\Lifecycle\WinBackOffer;
use App\Services\ActivationChecklist;
use Illuminate\Console\Command;

/**
 * The half of the customer lifecycle that nobody clicks.
 *
 * Payments, cancellations and suspensions announce themselves from inside
 * `BillingService` the moment they happen. The messages here are the
 * opposite: they exist precisely because *nothing* happened — a trial
 * running out, a checkout abandoned, a renewal approaching, an allowance
 * quietly filling up. Each one is a moment where silence costs money, for
 * the customer as much as for us.
 *
 * Every send is claimed through `lifecycle_messages` before it goes out, so
 * running this hourly, or twice by accident, or on three schedulers at once,
 * still produces exactly one email per subject.
 *
 * Restraint is a feature. At most: one trial warning, one renewal warning,
 * two invoice nudges, one suspension warning, two usage alerts per month and
 * one win-back — ever. A billing reminder that arrives twice reads as a
 * billing error.
 */
class RunLifecycle extends Command
{
    protected $signature = 'elitesender:lifecycle
                            {--dry-run : Report what would be sent without sending anything}';

    protected $description = 'Send trial, renewal, invoice, usage and win-back notices';

    private bool $dryRun = false;

    /** @var array<string, int> */
    private array $tally = [];

    public function handle(LifecycleMessenger $messenger, PlanGate $planGate): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if (! config('platform.lifecycle.enabled', true)) {
            $this->components->warn('Lifecycle messaging is switched off in platform settings.');

            return self::SUCCESS;
        }

        $this->activationStalled($messenger);
        $this->trialsEnding($messenger, $planGate);
        $this->cardsExpiring($messenger);
        $this->renewalsApproaching($messenger);
        $this->invoicesUnpaid($messenger);
        $this->suspensionsApproaching($messenger);
        $this->allowancesRunningOut($messenger, $planGate);
        $this->winBacks($messenger);

        foreach ($this->tally as $label => $count) {
            $this->components->twoColumnDetail(ucfirst($label), (string) $count);
        }

        if ($this->tally === []) {
            $this->components->info('Nothing to send.');
        }

        return self::SUCCESS;
    }

    /**
     * Signed up, never activated — the biggest leak in the funnel.
     *
     * A workspace that has not connected a relay cannot send, so it will
     * never renew and will churn without having been a customer in any
     * meaningful sense. One welcome email and then silence forever left that
     * whole cohort on the floor.
     *
     * Two nudges, and both stop the moment the blocking step is done. The
     * checklist is evaluated inside the workspace's own tenant context,
     * because every step it inspects is a tenant-scoped query.
     */
    private function activationStalled(LifecycleMessenger $messenger): void
    {
        $checklist = app(ActivationChecklist::class);

        foreach ([1 => 2, 2 => 6] as $nudge => $days) {
            Tenant::query()
                ->where('status', Tenant::STATUS_ACTIVE)
                ->whereBetween('created_at', [now()->subDays($days + 1), now()->subDays($days)])
                ->chunkById(100, function ($tenants) use ($messenger, $checklist, $nudge): void {
                    /** @var Tenant $tenant */
                    foreach ($tenants as $tenant) {
                        $owner = User::withoutGlobalScopes()
                            ->where('tenant_id', $tenant->getKey())
                            ->oldest()
                            ->first();

                        if ($owner === null) {
                            continue;
                        }

                        // Every step the checklist inspects is a
                        // tenant-scoped query, so it has to run inside the
                        // workspace; the owner is passed explicitly because
                        // there is no session out here.
                        $summary = \App\Tenancy\TenantContext::run(
                            $tenant,
                            fn () => $checklist->summary($owner),
                        );

                        // Activated already, or blocked only on verification
                        // (which has its own banner and its own email).
                        if ($summary['complete'] || $summary['next'] === null) {
                            continue;
                        }

                        if ($summary['next']['key'] === 'verify') {
                            continue;
                        }

                        $this->deliver(
                            $messenger,
                            $tenant,
                            sprintf('activation_nudge:%d', $nudge),
                            fn () => new ActivationNudge($summary['next'], $nudge),
                            'activation nudges',
                        );
                    }
                });
        }
    }

    /**
     * Cards that are about to stop working.
     *
     * Involuntary churn — a lapse nobody chose, caused by a card ageing out
     * — is the cheapest kind to prevent and the most infuriating to lose.
     * Warned before the expiry, so the fix is thirty seconds of admin rather
     * than a decline, a dunning cycle and an apology.
     */
    private function cardsExpiring(LifecycleMessenger $messenger): void
    {
        Subscription::withoutGlobalScopes()
            ->with('tenant')
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING, Subscription::STATUS_PAST_DUE])
            ->whereNotNull('gateway_token')
            ->whereNotNull('card_exp_month')
            ->whereNotNull('card_exp_year')
            ->chunkById(100, function ($subscriptions) use ($messenger): void {
                foreach ($subscriptions as $subscription) {
                    $expiresAt = $subscription->cardExpiresAt();

                    if ($subscription->tenant === null || $expiresAt === null) {
                        continue;
                    }

                    $expired = $expiresAt->isPast();

                    // Inside the warning window, or already gone.
                    if (! $expired && $expiresAt->greaterThan(now()->addDays(14))) {
                        continue;
                    }

                    $this->deliver(
                        $messenger,
                        $subscription->tenant,
                        sprintf('card_expiring:%s', $expiresAt->format('Y-m')),
                        fn () => new CardExpiring($subscription, $expired),
                        'card expiry notices',
                    );
                }
            });
    }

    /**
     * "Your trial ends in three days."
     *
     * Sent with notice rather than on the day: anyone who has to talk to
     * finance needs the lead time, and a trial that expires unannounced
     * converts into a support ticket instead of a subscription.
     */
    private function trialsEnding(LifecycleMessenger $messenger, PlanGate $planGate): void
    {
        $window = now()->addDays((int) config('platform.lifecycle.trial_ending_days', 3));

        Subscription::withoutGlobalScopes()
            ->with(['plan', 'tenant'])
            ->where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), $window])
            ->chunkById(100, function ($subscriptions) use ($messenger, $planGate): void {
                foreach ($subscriptions as $subscription) {
                    $tenant = $subscription->tenant;

                    if ($tenant === null) {
                        continue;
                    }

                    $this->deliver(
                        $messenger,
                        $tenant,
                        'trial_ending:'.$subscription->trial_ends_at?->toDateString(),
                        fn () => new TrialEnding($subscription, [
                            'contacts' => $planGate->usage($tenant, 'contacts'),
                            'emails_per_month' => $planGate->usage($tenant, 'emails_per_month'),
                        ]),
                        'trial notices',
                    );
                }
            });
    }

    /**
     * "We are about to charge your card."
     *
     * Only where a charge will genuinely be attempted — a saved credential
     * on a plan that costs money — otherwise it is a monthly lie. An
     * unannounced recurring debit is the most common chargeback trigger
     * there is, and a chargeback costs the fee, the revenue and a mark
     * against the merchant account.
     */
    private function renewalsApproaching(LifecycleMessenger $messenger): void
    {
        $window = now()->addDays((int) config('platform.lifecycle.renewal_reminder_days', 3));

        Subscription::withoutGlobalScopes()
            ->with(['plan', 'tenant'])
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNull('canceled_at')
            ->where('amount', '>', 0)
            ->whereNotNull('gateway_token')
            ->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [now(), $window])
            ->chunkById(100, function ($subscriptions) use ($messenger): void {
                foreach ($subscriptions as $subscription) {
                    if ($subscription->tenant === null || ! $subscription->canAutoRenew()) {
                        continue;
                    }

                    $this->deliver(
                        $messenger,
                        $subscription->tenant,
                        'renewal_reminder:'.$subscription->current_period_end?->toDateString(),
                        fn () => new RenewalReminder($subscription),
                        'renewal notices',
                    );
                }
            });
    }

    /**
     * Abandoned-checkout recovery, then a due-soon nudge.
     *
     * An invoice still open a day after it was raised nearly always means
     * the customer opened the payment page and was interrupted. Recovering
     * that is the highest-return message in the whole lifecycle, and the
     * entire trick is linking straight back to the invoice instead of to a
     * generic billing screen.
     */
    private function invoicesUnpaid(LifecycleMessenger $messenger): void
    {
        /** @var list<int> $schedule */
        $schedule = (array) config('platform.lifecycle.invoice_nudge_days', [1, 3]);

        foreach ($schedule as $index => $days) {
            $nudge = $index + 1;

            Invoice::withoutGlobalScopes()
                ->with(['plan', 'tenant'])
                ->where('status', Invoice::STATUS_OPEN)
                ->where('created_at', '<=', now()->subDays((int) $days))
                // Somebody actively paying is not somebody to chase.
                ->whereDoesntHave('payments', fn ($q) => $q->where('status', \App\Models\Payment::STATUS_SUCCEEDED))
                ->chunkById(100, function ($invoices) use ($messenger, $nudge): void {
                    foreach ($invoices as $invoice) {
                        if ($invoice->tenant === null || $invoice->balance() <= 0) {
                            continue;
                        }

                        $this->deliver(
                            $messenger,
                            $invoice->tenant,
                            sprintf('invoice_nudge:%d:%s', $nudge, $invoice->getKey()),
                            fn () => new InvoiceReminder($invoice, $nudge),
                            $nudge === 1 ? 'checkout recoveries' : 'invoice reminders',
                        );
                    }
                });
        }
    }

    /**
     * The last email before sending stops.
     *
     * A suspension nobody saw coming turns a recoverable billing problem
     * into a cancellation. The date is stated exactly, and so is the fact
     * that nothing gets deleted — that fear is what drives the angry ticket.
     */
    private function suspensionsApproaching(LifecycleMessenger $messenger): void
    {
        $graceDays = (int) config('billing.grace_days', 7);
        $warnDays = (int) config('platform.lifecycle.suspension_warning_days', 2);

        Subscription::withoutGlobalScopes()
            ->with('tenant')
            ->where('status', Subscription::STATUS_PAST_DUE)
            ->whereNotNull('current_period_end')
            ->chunkById(100, function ($subscriptions) use ($messenger, $graceDays, $warnDays): void {
                foreach ($subscriptions as $subscription) {
                    $pausesAt = $subscription->current_period_end?->copy()->addDays($graceDays);

                    if ($subscription->tenant === null || $pausesAt === null) {
                        continue;
                    }

                    // Only inside the warning window, and never after the fact.
                    if ($pausesAt->isPast() || $pausesAt->greaterThan(now()->addDays($warnDays))) {
                        continue;
                    }

                    $this->deliver(
                        $messenger,
                        $subscription->tenant,
                        'suspension_warning:'.$pausesAt->toDateString(),
                        fn () => new SuspensionWarning($subscription, $pausesAt),
                        'suspension warnings',
                    );
                }
            });
    }

    /**
     * Usage alerts at 80% and 100% of the monthly send allowance.
     *
     * A customer who discovers their cap mid-campaign discovers it because
     * recipients stopped receiving mail — the worst possible moment, and
     * entirely our fault for saying nothing at 80%. Warning early converts
     * to an upgrade; blocking late converts to a complaint.
     */
    private function allowancesRunningOut(LifecycleMessenger $messenger, PlanGate $planGate): void
    {
        /** @var list<int> $thresholds */
        $thresholds = (array) config('platform.lifecycle.usage_alert_thresholds', [80, 100]);
        rsort($thresholds);

        $period = now()->format('Y-m');

        Subscription::withoutGlobalScopes()
            ->with(['plan', 'tenant'])
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING, Subscription::STATUS_PAST_DUE])
            ->chunkById(100, function ($subscriptions) use ($messenger, $planGate, $thresholds, $period): void {
                foreach ($subscriptions as $subscription) {
                    $tenant = $subscription->tenant;

                    if ($tenant === null) {
                        continue;
                    }

                    $limit = $planGate->limitFor($tenant, 'emails_per_month');

                    // Unlimited plans, and plans that allow nothing, have no
                    // threshold worth announcing.
                    if ($limit === null || $limit <= 0) {
                        continue;
                    }

                    $used = $planGate->usage($tenant, 'emails_per_month');
                    $percent = (int) floor($used / $limit * 100);

                    foreach ($thresholds as $threshold) {
                        if ($percent < $threshold) {
                            continue;
                        }

                        // Highest crossed threshold only: nobody needs "80%"
                        // and "100%" in the same minute.
                        $this->deliver(
                            $messenger,
                            $tenant,
                            sprintf('usage_alert:%d:%s', $threshold, $period),
                            fn () => new UsageThresholdReached($subscription, $threshold, $used, $limit),
                            'usage alerts',
                        );

                        break;
                    }
                }
            });
    }

    /**
     * One check-in, a week after a workspace dropped to the free plan.
     *
     * Long enough that the decision has been lived with, short enough that
     * the data is still current. It asks a question rather than making a
     * pitch: the reply is worth more than the reactivation.
     */
    private function winBacks(LifecycleMessenger $messenger): void
    {
        $days = (int) config('platform.lifecycle.win_back_days', 7);

        Subscription::withoutGlobalScopes()
            ->with(['plan', 'tenant'])
            ->whereNotNull('downgraded_at')
            ->where('downgraded_at', '<=', now()->subDays($days))
            ->where('downgraded_at', '>=', now()->subDays($days + 7))
            ->chunkById(100, function ($subscriptions) use ($messenger): void {
                foreach ($subscriptions as $subscription) {
                    if ($subscription->tenant === null) {
                        continue;
                    }

                    $this->deliver(
                        $messenger,
                        $subscription->tenant,
                        'win_back:'.$subscription->downgraded_at?->toDateString(),
                        fn () => new WinBackOffer($subscription),
                        'win-backs',
                    );
                }
            });
    }

    /**
     * @param  callable(): \Illuminate\Notifications\Notification  $factory
     */
    private function deliver(
        LifecycleMessenger $messenger,
        Tenant $tenant,
        string $key,
        callable $factory,
        string $label,
    ): void {
        if ($this->dryRun) {
            if (! $messenger->alreadySent($tenant, $key)) {
                $this->line("  would send <fg=cyan>{$key}</> to {$tenant->name}");
                $this->tally[$label] = ($this->tally[$label] ?? 0) + 1;
            }

            return;
        }

        if ($messenger->sendOnce($tenant, $key, $factory)) {
            $this->tally[$label] = ($this->tally[$label] ?? 0) + 1;
        }
    }
}
