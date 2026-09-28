<?php

namespace App\Http\Controllers\Admin;

use App\Billing\BillingService;
use App\Billing\PlanGate;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Plan;
use App\Models\SmtpAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly PlanGate $planGate,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        return view('admin.tenants.index', [
            'tenants' => Tenant::query()
                ->withCount(['users', 'campaigns'])
                ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function show(string $id): View
    {
        $tenant = Tenant::withCount('users')->findOrFail($id);

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'users' => User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get(),
            'subscription' => $this->planGate->subscriptionFor($tenant),
            'usage' => $this->planGate->snapshot($tenant),
            'plans' => Plan::active()->orderBy('sort_order')->get(),
            'invoices' => \App\Models\Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)->latest()->limit(20)->get(),
            'counts' => [
                'contacts' => Contact::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deleted_at')->count(),
                'campaigns' => Campaign::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deleted_at')->count(),
                'relays' => SmtpAccount::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deleted_at')->count(),
            ],
        ]);
    }

    /** Suspend or reinstate a workspace. Suspension stops access, never deletes data. */
    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:'.Tenant::STATUS_ACTIVE.','.Tenant::STATUS_SUSPENDED],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant->forceFill(['status' => $validated['status']])->save();

        Log::warning('Admin changed workspace status', [
            'admin_id' => auth('admin')->id(),
            'tenant_id' => $tenant->id,
            'status' => $validated['status'],
            'reason' => $validated['reason'] ?? null,
        ]);

        return back()->with('success', "Workspace {$validated['status']}.");
    }

    /**
     * Move a workspace onto a plan without charging for it.
     *
     * This is the lever for "we agreed a custom deal" and for making a
     * customer whole after an outage — both real, and both otherwise
     * impossible without editing the database by hand.
     */
    public function changePlan(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        $this->billing->activate($tenant, $plan, $this->billing->currencyFor($tenant), gateway: 'admin');

        Log::warning('Admin changed workspace plan', [
            'admin_id' => auth('admin')->id(),
            'tenant_id' => $tenant->id,
            'plan' => $plan->code,
            'reason' => $validated['reason'] ?? null,
        ]);

        return back()->with('success', "Workspace moved to {$plan->name}.");
    }
}
