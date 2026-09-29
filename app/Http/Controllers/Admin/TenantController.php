<?php

namespace App\Http\Controllers\Admin;

use App\Billing\BillingService;
use App\Billing\PlanGate;
use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Plan;
use App\Models\SmtpAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Platform\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly PlanGate $planGate,
        private readonly ActivityLogger $activity,
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
            // "Did they get the warning?" is the first question support asks
            // about any billing complaint, and before this the honest answer
            // was "check the mail provider's logs, if they still exist".
            'lifecycleMessages' => \App\Models\LifecycleMessage::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->latest('sent_at')
                ->limit(15)
                ->get(),
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

        $before = $tenant->status;
        $tenant->forceFill(['status' => $validated['status']])->save();

        $this->activity->record(
            action: $validated['status'] === Tenant::STATUS_SUSPENDED ? 'tenant.suspend' : 'tenant.reinstate',
            description: sprintf('%s workspace “%s”', $validated['status'] === Tenant::STATUS_SUSPENDED ? 'Suspended' : 'Reinstated', $tenant->name),
            subject: $tenant,
            tenantId: $tenant->getKey(),
            severity: AdminActivity::SEVERITY_CRITICAL,
            reason: $validated['reason'] ?? null,
            changes: ['from' => $before, 'to' => $validated['status']],
        );

        return back()->with('success', $validated['status'] === Tenant::STATUS_SUSPENDED
            ? 'Workspace suspended. Their data is untouched.'
            : 'Workspace reinstated.');
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

        $before = $this->planGate->subscriptionFor($tenant)?->plan?->name;

        $this->billing->activate($tenant, $plan, $this->billing->currencyFor($tenant), gateway: 'admin');

        $this->activity->record(
            action: 'tenant.change_plan',
            description: sprintf('Moved “%s” from %s to %s at no charge', $tenant->name, $before ?? 'no plan', $plan->name),
            subject: $tenant,
            tenantId: $tenant->getKey(),
            severity: AdminActivity::SEVERITY_CRITICAL,
            reason: $validated['reason'] ?? null,
            changes: ['from' => $before, 'to' => $plan->name],
        );

        return back()->with('success', "Workspace moved to {$plan->name} at no charge.");
    }
}
