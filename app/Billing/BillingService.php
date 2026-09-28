<?php

namespace App\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
    ) {}

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

        return DB::transaction(function () use ($tenant, $plan, $currency, $price): Invoice {
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

            $this->settle($invoice);

            return $payment;
        });
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
    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->forceFill([
            'cancel_at' => $subscription->current_period_end ?? now(),
            'canceled_at' => now(),
        ])->save();

        return $subscription;
    }

    public function gateways(): PaymentGatewayManager
    {
        return $this->gateways;
    }
}
