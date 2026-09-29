<?php

namespace App\Http\Controllers\Billing;

use App\Billing\BillingService;
use App\Billing\PlanGate;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Role;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly PlanGate $planGate,
    ) {}

    public function index(): View
    {
        $tenant = $this->tenant();

        $currency = $this->billing->currencyFor($tenant);

        return view('billing.index', [
            'tenant' => $tenant,
            'currency' => $currency,
            'subscription' => $this->planGate->subscriptionFor($tenant),
            'usage' => $this->planGate->snapshot($tenant),
            'plans' => Plan::public()->orderBy('sort_order')->get(),
            'card' => \App\Billing\StoredCredential::fromSubscription(
                $this->planGate->subscriptionFor($tenant) ?? new \App\Models\Subscription,
            ),
            'invoices' => Invoice::query()->latest()->limit(24)->get(),
            'gateways' => $this->billing->gateways()->availableFor($currency),
        ]);
    }

    /**
     * Choose a plan. Free plans switch immediately; paid plans raise an
     * invoice and send the customer to checkout.
     */
    public function subscribe(Request $request): RedirectResponse
    {
        $this->authorizeBilling();

        $validated = $request->validate([
            'plan' => ['required', 'string', 'exists:plans,code'],
        ]);

        $plan = Plan::active()->where('code', $validated['plan'])->firstOrFail();
        $tenant = $this->tenant();

        $existing = $this->planGate->subscriptionFor($tenant);

        try {
            // An existing subscriber is *changing* plan, not starting one:
            // upgrades are prorated to the unused part of the period, and
            // downgrades are scheduled for period end.
            $invoice = $existing === null
                ? $this->billing->invoiceForPlan($tenant, $plan)
                : $this->billing->changePlan($tenant, $plan);
        } catch (RuntimeException $e) {
            return back()->withErrors($e->getMessage());
        }

        if ($invoice !== null) {
            return redirect()->route('billing.invoices.show', $invoice->id);
        }

        $refreshed = $this->planGate->subscriptionFor($tenant);

        if ($refreshed?->pending_plan_id === $plan->id) {
            return redirect()->route('billing.index')->with('success', sprintf(
                'You will move to %s on %s. Nothing changes before then — you keep everything you have already paid for.',
                $plan->name,
                $refreshed->current_period_end?->toFormattedDayDateString() ?? 'your next renewal',
            ));
        }

        return redirect()->route('billing.index')
            ->with('success', "You're now on the {$plan->name} plan.");
    }

    public function showInvoice(string $id): View
    {
        $invoice = Invoice::with(['plan', 'payments'])->findOrFail($id);

        $gateways = $this->billing->gateways()->availableFor($invoice->currency);

        return view('billing.invoice', [
            'invoice' => $invoice,
            'gateways' => $gateways,
            'manual' => $this->billing->gateways()->has('manual')
                ? $this->billing->gateways()->get('manual')
                : null,
        ]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->authorizeBilling();

        $subscription = $this->planGate->subscriptionFor($this->tenant());

        if ($subscription === null) {
            return back()->withErrors('There is no active subscription to cancel.');
        }

        // Captured, not demanded: the reason is a select with an "other"
        // escape hatch, and the note is optional. Asking a leaving customer
        // to write an essay is how you get an empty field and a bad memory.
        $validated = $request->validate([
            'reason' => ['nullable', 'string', Rule::in(array_keys(\App\Models\Subscription::CANCELLATION_REASONS))],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->billing->cancel(
            $subscription,
            $validated['reason'] ?? null,
            $validated['feedback'] ?? null,
        );

        // Deliberately not immediate: the customer has paid through the end of
        // the period and taking that away is theft, however small.
        return back()->with('success', sprintf(
            'Your plan will not renew. You keep everything until %s, and you can undo this any time before then.',
            $subscription->current_period_end?->toFormattedDayDateString() ?? 'the period ends',
        ));
    }

    /**
     * Undo a scheduled cancellation.
     *
     * One click, no card re-entry, no new invoice. Making someone re-subscribe
     * from scratch to reverse a mis-click turns a save into a churn.
     */
    public function resume(): RedirectResponse
    {
        $this->authorizeBilling();

        $subscription = $this->planGate->subscriptionFor($this->tenant());

        if ($subscription === null || ! $subscription->isEnding()) {
            return back()->withErrors('There is no scheduled cancellation to undo.');
        }

        $this->billing->resume($subscription);

        return back()->with('success', 'Welcome back — your plan will keep renewing as normal.');
    }

    /** Cancel a scheduled downgrade before it takes effect. */
    public function keepPlan(): RedirectResponse
    {
        $this->authorizeBilling();

        $subscription = $this->planGate->subscriptionFor($this->tenant());

        if ($subscription?->pending_plan_id === null) {
            return back()->withErrors('There is no scheduled plan change to undo.');
        }

        $subscription->forceFill(['pending_plan_id' => null])->save();

        return back()->with('success', 'Scheduled change cancelled — you stay on your current plan.');
    }

    /** Forget the stored card. Renewals then fall back to an emailed invoice. */
    public function forgetCard(): RedirectResponse
    {
        $this->authorizeBilling();

        $subscription = $this->planGate->subscriptionFor($this->tenant());

        if ($subscription === null || blank($subscription->gateway_token)) {
            return back()->withErrors('There is no saved payment method.');
        }

        $subscription->forceFill([
            'gateway_token' => null,
            'gateway_customer' => null,
            'card_brand' => null,
            'card_last_four' => null,
            'card_exp_month' => null,
            'card_exp_year' => null,
        ])->save();

        return back()->with('success', 'Payment method removed. We will email you an invoice before each renewal.');
    }

    /** Only owners and admins may commit the workspace to spending money. */
    private function authorizeBilling(): void
    {
        abort_unless(auth()->user()?->hasRoleAtLeast(Role::ADMIN) ?? false, 403);
    }

    private function tenant(): \App\Models\Tenant
    {
        $tenant = TenantContext::tenant();

        abort_if($tenant === null, 403);

        return $tenant;
    }
}
