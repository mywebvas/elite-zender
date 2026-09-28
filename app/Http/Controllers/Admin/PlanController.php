<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Pricing control.
 *
 * Plans live in the database precisely so pricing changes do not need a
 * deploy — but they are super-admin only, because a wrong number here bills
 * every customer incorrectly.
 */
class PlanController extends Controller
{
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

        Log::warning('Admin updated a plan', [
            'admin_id' => auth('admin')->id(),
            'plan' => $plan->code,
            'changes' => $plan->getChanges(),
        ]);

        return back()->with('success', "{$plan->name} updated.");
    }
}
