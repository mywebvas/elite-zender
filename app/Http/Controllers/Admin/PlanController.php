<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use App\Models\Plan;
use App\Platform\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pricing control.
 *
 * Plans live in the database precisely so pricing changes do not need a
 * deploy — but they are super-admin only, because a wrong number here bills
 * every customer incorrectly.
 */
class PlanController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::query()->withCount('subscriptions')->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $plan = Plan::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            // Amounts are MINOR units (kobo / cents) — never floats.
            'price_ngn' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'price_usd' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'limits.contacts' => ['nullable', 'integer', 'min:0'],
            'limits.emails_per_month' => ['nullable', 'integer', 'min:0'],
            'limits.smtp_accounts' => ['nullable', 'integer', 'min:0'],
            'limits.users' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        // An absent limit means unlimited; an explicit 0 means none allowed.
        // array_filter would erase that distinction, so filter on null only.
        $limits = array_filter(
            $validated['limits'] ?? [],
            static fn ($value) => $value !== null && $value !== '',
        );

        $plan->update([
            ...$validated,
            'limits' => $limits === [] ? null : $limits,
            'is_active' => $request->boolean('is_active'),
            'is_public' => $request->boolean('is_public'),
        ]);

        if ($plan->wasChanged()) {
            $this->activity->record(
                action: 'plan.update',
                description: "Updated the {$plan->name} plan",
                subject: $plan,
                severity: AdminActivity::SEVERITY_CRITICAL,
                changes: $plan->getChanges(),
            );
        }

        return back()->with('success', "{$plan->name} updated.");
    }

    /**
     * Create a plan.
     *
     * New plans start private so pricing can be set and checked before any
     * customer can pick them up from the billing page.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/', 'unique:plans,code'],
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'price_ngn' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'price_usd' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'limits.contacts' => ['nullable', 'integer', 'min:0'],
            'limits.emails_per_month' => ['nullable', 'integer', 'min:0'],
            'limits.smtp_accounts' => ['nullable', 'integer', 'min:0'],
            'limits.users' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'code.regex' => 'Use lowercase letters, numbers, dashes and underscores only.',
        ]);

        $limits = array_filter($validated['limits'] ?? [], static fn ($v) => $v !== null && $v !== '');

        /** @var Plan $plan */
        $plan = Plan::query()->create([
            ...$validated,
            'limits' => $limits === [] ? null : $limits,
            'is_active' => true,
            // Private until an operator deliberately publishes it.
            'is_public' => false,
        ]);

        $this->activity->record(
            action: 'plan.create',
            description: "Created the {$plan->name} plan",
            subject: $plan,
            severity: AdminActivity::SEVERITY_CRITICAL,
        );

        return back()->with('success', "{$plan->name} created. It stays hidden until you mark it public.");
    }

    /**
     * Archive a plan.
     *
     * Never a hard delete while anyone is subscribed: invoices reference the
     * plan, and removing the row would orphan a customer's billing history.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        /** @var Plan $plan */
        $plan = Plan::query()->withCount('subscriptions')->findOrFail($id);

        if ($plan->subscriptions_count > 0) {
            $plan->update(['is_active' => false, 'is_public' => false]);

            $this->activity->record(
                action: 'plan.archive',
                description: "Archived the {$plan->name} plan ({$plan->subscriptions_count} subscriber(s) kept on it)",
                subject: $plan,
                severity: AdminActivity::SEVERITY_CRITICAL,
                reason: $request->string('reason')->toString() ?: null,
            );

            return back()->with('success', "{$plan->name} archived. Existing subscribers keep it; nobody new can choose it.");
        }

        $name = $plan->name;
        $plan->delete();

        $this->activity->record(
            action: 'plan.delete',
            description: "Deleted the {$name} plan (no subscribers)",
            severity: AdminActivity::SEVERITY_CRITICAL,
        );

        return back()->with('success', "{$name} deleted.");
    }
}
