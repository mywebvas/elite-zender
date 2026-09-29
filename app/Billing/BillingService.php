<?php

namespace App\Billing;

use App\Billing\Gateways\PaystackGateway;
use App\Billing\Gateways\StripeGateway;
use App\Lifecycle\LifecycleMessenger;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The money state machine: subscribe, invoice, record payment, activate.
 *
 * Everything that touches a balance runs inside a transaction and is keyed so
 * it can be replayed safely. Payment providers retry webhooks aggressively and
 * customers refresh callback pages; both must be harmless.
 */
final class BillingService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly LifecycleMessenger $messenger,
    ) {}

    /**
     * Tell the workspace what just happened to its money.
     *
     * Every notify() here is fire-and-forget by design: LifecycleMessenger
     * swallows and reports its own failures, so a mail outage can never roll
     * back a settled payment or stop a suspension from applying. Keys carry
     * the subject id, which is what makes a replayed webhook silent.
     *
     * @param  callable(): \Illuminate\Notifications\Notification  $factory
     */
    private function notify(?Tenant $tenant, string $key, callable $factory): void
    {
        if ($tenant === null) {
            return;
        }

        $this->messenger->sendOnce($tenant, $key, $factory);
    }

    /**
     * The currency a workspace should be billed in.
     *
     * Nigerian workspaces get naira: locally issued cards frequently fail on
     * USD charges, and quoting dollars to a naira-earning business is a
     * conversion killer.
     */
    public function currencyFor(Tenant $tenant): string
    {
        $country = strtoupper((string) $tenant->setting('country', ''));

        return in_array($country, (array) config('billing.ngn_countries', []), true)
            ? 'NGN'
            : (string) config('billing.default_currency', 'USD');
    }

    /**
     * Start a workspace on the free plan with a trial window.
     *
     * Returns null when no plan catalogue exists yet. Registration calls this,
     * and a fresh install with an unseeded `plans` table must still be able to
     * create an account — failing closed here would make the product
     * unusable before an operator has ever logged in.
     */
    public function startTrial(Tenant $tenant, ?Plan $plan = null): ?Subscription
    {
        $plan ??= Plan::active()->where('code', 'free')->first()
            ?? Plan::active()->orderBy('sort_order')->first();

        if ($plan === null) {
            return null;
        }

        $currency = $this->currencyFor($tenant);

        return Subscription::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getKey()],
            [
                'plan_id' => $plan->getKey(),
                'status' => Subscription::STATUS_TRIALING,
                'currency' => $currency,
                'amount' => $plan->priceFor($currency) ?? 0,
                'trial_ends_at' => now()->addDays((int) config('billing.trial_days', 14)),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays((int) config('billing.trial_days', 14)),
            ],
        );
    }

    /**
     * Raise an invoice for moving a workspace onto a plan.
     *
     * Free plans switch immediately with no invoice; quoted (Enterprise) plans
     * cannot be self-served at all.
     */
    public function invoiceForPlan(Tenant $tenant, Plan $plan): ?Invoice
    {
        $currency = $this->currencyFor($tenant);
        $price = $plan->priceFor($currency);

        if ($plan->isQuoteOnly()) {
            throw new RuntimeException('This plan is quoted — please contact sales.');
        }

        if ($price === null || $price === 0) {
            $this->activate($tenant, $plan, $currency, gateway: null);

            return null;
        }

        $invoice = DB::transaction(function () use ($tenant, $plan, $currency, $price): Invoice {
            $subscription = Subscription::withoutGlobalScopes()
                ->firstWhere('tenant_id', $tenant->getKey());

            return Invoice::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->getKey(),
                'subscription_id' => $subscription?->getKey(),
                'plan_id' => $plan->getKey(),
                'number' => Invoice::nextNumber(),
                'status' => Invoice::STATUS_OPEN,
                'currency' => $currency,
                'subtotal' => $price,
                'tax' => 0,
                'total' => $price,
                'period_start' => now(),
                'period_end' => now()->addMonth(),
                'due_at' => now()->addDays(7),
                'line_items' => [[
                    'description' => "{$plan->name} plan — 1 month",
                    'quantity' => 1,
                    'unit_amount' => $price,
                    'amount' => $price,
                ]],
            ]);
        });

        $this->announceInvoice($tenant, $invoice);

        return $invoice;
    }

    /**
     * An invoice nobody was told about is an invoice nobody pays.
     *
     * This is also the first half of abandoned-checkout recovery: the email
     * carries a direct link back to the payment page, so a customer who was
     * interrupted mid-checkout has a one-click route back to it rather than
     * having to find their way through the app.
     */
    private function announceInvoice(?Tenant $tenant, Invoice $invoice): void
    {
        $tenant ??= Tenant::find($invoice->tenant_id);

        $this->notify(
            $tenant,
            'invoice_issued:'.$invoice->getKey(),
            fn () => new \App\Notifications\Lifecycle\InvoiceIssued($invoice->loadMissing('plan')),
        );
    }

    /**
     * Record a successful payment and settle the invoice.
     *
     * Idempotent on (gateway, gateway_ref), which the database enforces with a
     * unique index. A replayed webhook, a refreshed callback page and a manual
     * re-verification all converge on the same single credit.
     */
    public function recordPayment(Invoice $invoice, PaymentResult $result): Payment
    {
        // Amounts are minor units with no currency attached, so ₦1,000 and
        // $10.00 are both "1000" by the time settle() compares them against
        // the invoice total. Refusing the mismatch is the only safe answer:
        // silently crediting it would settle a dollar invoice with naira.
        if (strtoupper($result->currency) !== strtoupper($invoice->currency)) {
            Log::critical('Rejected a payment in the wrong currency', [
                'invoice' => $invoice->number,
                'invoice_currency' => $invoice->currency,
                'payment_currency' => $result->currency,
                'gateway' => $result->gateway,
                'gateway_ref' => $result->gatewayRef,
            ]);

            throw new RuntimeException(sprintf(
                'Payment currency [%s] does not match invoice %s [%s].',
                $result->currency,
                $invoice->number,
                $invoice->currency,
            ));
        }

        return DB::transaction(function () use ($invoice, $result): Payment {
            $existing = Payment::withoutGlobalScopes()
                ->where('gateway', $result->gateway)
                ->where('gateway_ref', $result->gatewayRef)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                Log::info('Duplicate payment notification ignored', [
                    'gateway' => $result->gateway,
                    'gateway_ref' => $result->gatewayRef,
                    'invoice' => $invoice->number,
                ]);

                return $existing;
            }

            $payment = Payment::withoutGlobalScopes()->create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->getKey(),
                'gateway' => $result->gateway,
                'reference' => $result->reference,
                'gateway_ref' => $result->gatewayRef,
                'status' => Payment::STATUS_SUCCEEDED,
                'currency' => $result->currency,
                'amount' => $result->amount,
                'paid_at' => now(),
                'payload' => $result->raw,
            ]);

            // Order matters: settle() is what creates the subscription on a
            // first purchase, and there is nothing to attach a card to before
            // it exists. Getting this backwards meant the very first renewal
            // failed for every new customer.
            $wasSuspended = Subscription::withoutGlobalScopes()
                ->where('tenant_id', $invoice->tenant_id)
                ->where('status', Subscription::STATUS_EXPIRED)
                ->exists();

            $this->settle($invoice);
            $this->rememberCredential($invoice, $result);

            $this->acknowledgePayment($invoice->refresh(), $payment, $wasSuspended);

            return $payment;
        });
    }

    /**
     * Receipt, and — where it applies — the all-clear.
     *
     * Somebody who has just paid to end an outage wants one fact confirmed:
     * that it is over. Making them log in to find out is how a recovered
     * account becomes a cancelled one.
     */
    private function acknowledgePayment(Invoice $invoice, Payment $payment, bool $wasSuspended): void
    {
        $tenant = Tenant::find($invoice->tenant_id);

        if ($tenant === null) {
            return;
        }

        $this->notify(
            $tenant,
            'payment_received:'.$payment->getKey(),
            fn () => new \App\Notifications\Lifecycle\PaymentReceived($invoice->loadMissing('plan'), $payment),
        );

        if (! $wasSuspended || ! $invoice->isPaid()) {
            return;
        }

        $subscription = Subscription::withoutGlobalScopes()
            ->with('plan')
            ->firstWhere('tenant_id', $tenant->getKey());

        if ($subscription !== null) {
            $this->notify(
                $tenant,
                'reinstated:'.$payment->getKey(),
                fn () => new \App\Notifications\Lifecycle\WorkspaceReinstated($subscription),
            );
        }
    }

    /** Apply the ledger to the invoice and activate the plan once covered. */
    public function settle(Invoice $invoice): void
    {
        // Both SUCCEEDED and REFUNDED rows count: a refund is recorded as a
        // separate negative entry, so excluding the original would subtract
        // the money twice and zero out a partially refunded invoice.
        $paid = (int) Payment::withoutGlobalScopes()
            ->where('invoice_id', $invoice->getKey())
            ->whereIn('status', [Payment::STATUS_SUCCEEDED, Payment::STATUS_REFUNDED])
            ->sum('amount');

        $covered = $paid >= $invoice->total;

        $invoice->forceFill([
            'amount_paid' => max(0, $paid),
            // A partial refund puts the invoice back in arrears rather than
            // leaving it marked paid.
            'status' => $covered
                ? Invoice::STATUS_PAID
                : ($invoice->status === Invoice::STATUS_PAID ? Invoice::STATUS_OPEN : $invoice->status),
            'paid_at' => $covered ? ($invoice->paid_at ?? now()) : null,
        ])->save();

        if ($invoice->status !== Invoice::STATUS_PAID) {
            return;
        }

        $tenant = Tenant::find($invoice->tenant_id);
        $plan = Plan::find($invoice->plan_id);

        if ($tenant !== null && $plan !== null) {
            $this->activate($tenant, $plan, $invoice->currency, $invoice->payments()->latest()->value('gateway'));
        }
    }

    /** Move a workspace onto a plan for a fresh period. */
    public function activate(Tenant $tenant, Plan $plan, string $currency, ?string $gateway): Subscription
    {
        return Subscription::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getKey()],
            [
                'plan_id' => $plan->getKey(),
                'status' => Subscription::STATUS_ACTIVE,
                'currency' => $currency,
                'amount' => $plan->priceFor($currency) ?? 0,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'cancel_at' => null,
                'canceled_at' => null,
                'gateway' => $gateway,
            ],
        );
    }

    /**
     * Cancel at period end rather than immediately — the customer has paid for
     * the rest of the month and taking it away is theft, however small.
     */
    public function cancel(Subscription $subscription, ?string $reason = null, ?string $feedback = null): Subscription
    {
        $subscription->forceFill([
            'cancel_at' => $subscription->current_period_end ?? now(),
            'canceled_at' => now(),
            'pending_plan_id' => null,
            // Churn you cannot attribute is churn you cannot fix.
            'cancellation_reason' => $reason,
            'cancellation_feedback' => $feedback,
        ])->save();

        $this->notify(
            Tenant::find($subscription->tenant_id),
            'cancelled:'.($subscription->cancel_at?->toDateString() ?? now()->toDateString()),
            fn () => new \App\Notifications\Lifecycle\SubscriptionCancelled($subscription->loadMissing('plan')),
        );

        return $subscription;
    }

    /**
     * Undo a scheduled cancellation.
     *
     * One click, no re-entry of card details, no new invoice: the period the
     * customer already paid for simply keeps running. Making someone re-subscribe
     * from scratch to undo a mis-click is how you turn a save into a churn.
     */
    public function resume(Subscription $subscription): Subscription
    {
        $subscription->forceFill([
            'cancel_at' => null,
            'canceled_at' => null,
            'status' => $subscription->status === Subscription::STATUS_CANCELED
                ? Subscription::STATUS_ACTIVE
                : $subscription->status,
        ])->save();

        return $subscription;
    }

    /**
     * Move between paid plans mid-cycle.
     *
     * Upgrades take effect immediately and are invoiced for the *unused* part
     * of the period only — charging a full month for six remaining days is the
     * kind of thing customers notice once and never forgive. Downgrades are
     * scheduled for period end, because the capacity has already been bought.
     *
     * @return Invoice|null an invoice when money is owed now, otherwise null
     */
    public function changePlan(Tenant $tenant, Plan $target): ?Invoice
    {
        $subscription = Subscription::withoutGlobalScopes()->firstWhere('tenant_id', $tenant->getKey());

        if ($subscription === null) {
            return $this->invoiceForPlan($tenant, $target);
        }

        if ($target->isQuoteOnly()) {
            throw new RuntimeException('This plan is quoted — please contact sales.');
        }

        $currency = $subscription->currency;
        $currentPrice = $subscription->amount;
        $targetPrice = $target->priceFor($currency) ?? 0;

        // Same price, or moving to something cheaper: schedule it, do not bill.
        if ($targetPrice <= $currentPrice) {
            $subscription->forceFill([
                'pending_plan_id' => $target->getKey() === $subscription->plan_id ? null : $target->getKey(),
            ])->save();

            if ($targetPrice === 0 && $currentPrice === 0) {
                $this->activate($tenant, $target, $currency, $subscription->gateway);
            }

            return null;
        }

        $amount = $this->prorate($subscription, $targetPrice);

        if ($amount <= 0) {
            $this->activate($tenant, $target, $currency, $subscription->gateway);

            return null;
        }

        $invoice = DB::transaction(fn (): Invoice => Invoice::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'subscription_id' => $subscription->getKey(),
            'plan_id' => $target->getKey(),
            'number' => Invoice::nextNumber(),
            'status' => Invoice::STATUS_OPEN,
            'reason' => Invoice::REASON_UPGRADE,
            'currency' => $currency,
            'subtotal' => $amount,
            'tax' => 0,
            'total' => $amount,
            'period_start' => now(),
            'period_end' => $subscription->current_period_end ?? now()->addMonth(),
            'due_at' => now()->addDays(3),
            'line_items' => [[
                'description' => sprintf(
                    'Upgrade to %s — prorated for the remaining %d day(s) of this period',
                    $target->name,
                    $this->daysRemaining($subscription),
                ),
                'quantity' => 1,
                'unit_amount' => $amount,
                'amount' => $amount,
            ]],
        ]));

        $this->announceInvoice($tenant, $invoice);

        return $invoice;
    }

    /**
     * The difference in price for the unused part of the current period.
     *
     * Rounded down deliberately: when the arithmetic is ambiguous, the rounding
     * error should land in the customer's favour.
     */
    public function prorate(Subscription $subscription, int $targetPrice): int
    {
        $remaining = $this->daysRemaining($subscription);
        $periodDays = max(1, (int) ($subscription->current_period_start?->diffInDays($subscription->current_period_end) ?: 30));

        $difference = $targetPrice - $subscription->amount;

        return (int) floor($difference * min($remaining, $periodDays) / $periodDays);
    }

    private function daysRemaining(Subscription $subscription): int
    {
        if ($subscription->current_period_end === null) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInDays($subscription->current_period_end, absolute: false)));
    }

    /**
     * Raise the invoice for the next period.
     *
     * Separate from charging it: an invoice exists whether or not a card is on
     * file, which is what makes offline payers first-class rather than an
     * afterthought.
     */
    public function invoiceRenewal(Subscription $subscription): ?Invoice
    {
        $plan = $subscription->pendingPlan ?? $subscription->plan;

        if ($plan === null) {
            return null;
        }

        $price = $plan->priceFor($subscription->currency) ?? 0;

        if ($price === 0) {
            // Free plans just roll over.
            $this->rollPeriod($subscription, $plan, $price);

            return null;
        }

        $invoice = DB::transaction(function () use ($subscription, $plan, $price): Invoice {
            return Invoice::withoutGlobalScopes()->create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->getKey(),
                'plan_id' => $plan->getKey(),
                'number' => Invoice::nextNumber(),
                'status' => Invoice::STATUS_OPEN,
                'reason' => Invoice::REASON_RENEWAL,
                'currency' => $subscription->currency,
                'subtotal' => $price,
                'tax' => 0,
                'total' => $price,
                'period_start' => $subscription->current_period_end ?? now(),
                'period_end' => ($subscription->current_period_end ?? now())->copy()->addMonth(),
                'due_at' => now()->addDays((int) config('billing.grace_days', 7)),
                'line_items' => [[
                    'description' => "{$plan->name} plan — 1 month",
                    'quantity' => 1,
                    'unit_amount' => $price,
                    'amount' => $price,
                ]],
            ]);
        });

        $this->announceInvoice($subscription->tenant, $invoice);

        return $invoice;
    }

    /**
     * Attempt the stored-card charge for a renewal invoice.
     *
     * Returns true when the money landed. A failure is never fatal here: the
     * invoice stays open and dunning takes over.
     */
    public function attemptAutoCharge(Subscription $subscription, Invoice $invoice): bool
    {
        $credential = StoredCredential::fromSubscription($subscription);

        if ($credential === null || $subscription->gateway === null || ! $this->gateways->has($subscription->gateway)) {
            return false;
        }

        $gateway = $this->gateways->get($subscription->gateway);

        if (! $gateway->supportsRecurring() || ! $gateway->isConfigured()) {
            return false;
        }

        try {
            $result = $gateway->chargeStored($invoice, $credential);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        if (! $result->successful) {
            $this->recordDunningFailure($subscription, $invoice, $result->failureReason);

            return false;
        }

        $this->recordPayment($invoice, $result);

        $subscription->forceFill(['dunning_attempts' => 0, 'next_retry_at' => null])->save();

        return true;
    }

    /**
     * Log a failed renewal and schedule the next attempt.
     *
     * The schedule widens (1, 3, then 5 days) because an immediate retry on a
     * declined card almost always declines again, and each attempt can cost the
     * customer a bank notification.
     */
    public function recordDunningFailure(Subscription $subscription, Invoice $invoice, ?string $reason): void
    {
        $attempts = $subscription->dunning_attempts + 1;

        $schedule = [1, 3, 5];
        $next = $schedule[$attempts - 1] ?? null;

        $subscription->forceFill([
            'status' => Subscription::STATUS_PAST_DUE,
            'dunning_attempts' => $attempts,
            'next_retry_at' => $next === null ? null : now()->addDays($next),
        ])->save();

        Payment::withoutGlobalScopes()->create([
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->getKey(),
            'gateway' => (string) $subscription->gateway,
            'reference' => 'dunning_'.$attempts.'_'.$invoice->getKey(),
            'status' => Payment::STATUS_FAILED,
            'currency' => $invoice->currency,
            'amount' => 0,
            'failure_reason' => $reason ?? 'The card was declined.',
        ]);

        Log::warning('Renewal charge failed', [
            'tenant_id' => $subscription->tenant_id,
            'invoice' => $invoice->number,
            'attempt' => $attempts,
            'reason' => $reason,
        ]);

        // Dunning used to stop at this log line. Most failed renewals are an
        // expired card — a thirty-second fix for the customer, but only if
        // somebody tells them it happened.
        $this->notify(
            Tenant::find($subscription->tenant_id),
            sprintf('payment_failed:%s:%d', $invoice->getKey(), $attempts),
            fn () => new \App\Notifications\Lifecycle\PaymentFailed(
                $subscription->refresh(),
                $invoice,
                $attempts,
                $reason,
            ),
        );
    }

    /** Move the subscription into its next period. */
    public function rollPeriod(Subscription $subscription, Plan $plan, int $amount): Subscription
    {
        $start = $subscription->current_period_end ?? now();

        $subscription->forceFill([
            'plan_id' => $plan->getKey(),
            'pending_plan_id' => null,
            'status' => Subscription::STATUS_ACTIVE,
            'amount' => $amount,
            'current_period_start' => $start,
            // Anchored to the previous period end, not to "now": renewing a day
            // late must not quietly shift the customer's billing date.
            'current_period_end' => $start->copy()->addMonth(),
            'dunning_attempts' => 0,
            'next_retry_at' => null,
        ])->save();

        return $subscription;
    }

    /**
     * Stop the workspace when an invoice has gone unpaid past the grace window.
     *
     * Access is suspended, never deleted — a customer who pays a week late
     * should find everything exactly as they left it.
     */
    public function lapse(Subscription $subscription): void
    {
        $subscription->forceFill([
            'status' => Subscription::STATUS_EXPIRED,
            'next_retry_at' => null,
        ])->save();

        Log::warning('Subscription lapsed after the grace window', [
            'tenant_id' => $subscription->tenant_id,
        ]);

        $this->notify(
            Tenant::find($subscription->tenant_id),
            'suspended:'.($subscription->current_period_end?->toDateString() ?? now()->toDateString()),
            fn () => new \App\Notifications\Lifecycle\WorkspaceSuspended,
        );
    }

    /** Persist a reusable credential so the next renewal needs no interaction. */
    private function rememberCredential(Invoice $invoice, PaymentResult $result): void
    {
        $subscription = Subscription::withoutGlobalScopes()->firstWhere('tenant_id', $invoice->tenant_id);

        if ($subscription === null) {
            return;
        }

        $credential = match ($result->gateway) {
            'paystack' => PaystackGateway::credentialFrom($result->raw),
            'stripe' => $this->gateways->has('stripe')
                ? $this->stripeCredential($result)
                : null,
            default => null,
        };

        if ($credential === null) {
            return;
        }

        $subscription->forceFill([
            'gateway' => $result->gateway,
            'gateway_token' => $credential->token,
            'gateway_customer' => $credential->customer,
            'card_brand' => $credential->brand,
            'card_last_four' => $credential->lastFour,
        ])->save();
    }

    private function stripeCredential(PaymentResult $result): ?StoredCredential
    {
        $gateway = $this->gateways->get('stripe');

        if (! $gateway instanceof StripeGateway) {
            return null;
        }

        try {
            return $gateway->credentialFromSession($result->raw);
        } catch (Throwable $e) {
            // Losing the card on file is a degraded renewal, not a failed
            // payment — never let it roll back the money we just took.
            report($e);

            return null;
        }
    }

    public function gateways(): PaymentGatewayManager
    {
        return $this->gateways;
    }
}
