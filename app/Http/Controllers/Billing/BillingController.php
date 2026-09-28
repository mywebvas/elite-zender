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

        try {
            $invoice = $this->billing->invoiceForPlan($tenant, $plan);
        } catch (RuntimeException $e) {
            return back()->withErrors($e->getMessage());
        }

        if ($invoice === null) {
            return redirect()->route('billing.index')
                ->with('success', "You're now on the {$plan->name} plan.");
        }

        return redirect()->route('billing.invoices.show', $invoice->id);
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

    public function cancel(): RedirectResponse
    {
        $this->authorizeBilling();

        $subscription = $this->planGate->subscriptionFor($this->tenant());

        if ($subscription === null) {
            return back()->withErrors('There is no active subscription to cancel.');
        }

        $this->billing->cancel($subscription);

        // Deliberately not immediate: the customer has paid through the end of
        // the period and taking that away is theft, however small.
        return back()->with('success', 'Your plan will not renew. You keep full access until the period ends.');
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
