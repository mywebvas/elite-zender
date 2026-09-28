<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Platform overview.
 *
 * Every query here is deliberately unscoped — this is the one place in the
 * application that is *supposed* to see across tenants, which is exactly why
 * it lives behind its own guard and its own table.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'tenants' => Tenant::query()->count(),
                'active_tenants' => Tenant::query()->where('status', Tenant::STATUS_ACTIVE)->count(),
                'users' => User::withoutGlobalScopes()->count(),
                'contacts' => Contact::withoutGlobalScopes()->whereNull('deleted_at')->count(),
                'campaigns' => Campaign::withoutGlobalScopes()->whereNull('deleted_at')->count(),
                'emails_sent' => (int) Campaign::withoutGlobalScopes()->sum('sent_count'),
            ],
            'revenue' => $this->revenue(),
            'subscriptions' => Subscription::withoutGlobalScopes()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'planBreakdown' => DB::table('subscriptions')
                ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->selectRaw('plans.name, count(*) as aggregate')
                ->groupBy('plans.name')
                ->orderByDesc('aggregate')
                ->pluck('aggregate', 'plans.name'),
            'awaitingReview' => Payment::withoutGlobalScopes()
                ->with(['tenant:id,name', 'invoice:id,number,currency,total'])
                ->where('gateway', 'manual')
                ->where('status', Payment::STATUS_PENDING)
                ->whereNotNull('proof_path')
                ->latest()
                ->limit(10)
                ->get(),
            'overdue' => Invoice::withoutGlobalScopes()
                ->with('tenant:id,name')
                ->where('status', Invoice::STATUS_OPEN)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->orderBy('due_at')
                ->limit(10)
                ->get(),
            'newestTenants' => Tenant::query()->withCount('users')->latest()->limit(8)->get(),
        ]);
    }

    /**
     * Collected revenue by currency.
     *
     * Deliberately not summed into one number: adding naira to dollars
     * produces a figure that is wrong in every currency.
     *
     * @return array<string, array{this_month: int, all_time: int}>
     */
    private function revenue(): array
    {
        $rows = Payment::withoutGlobalScopes()
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->selectRaw('currency, sum(amount) as all_time')
            ->selectRaw('sum(case when paid_at >= ? then amount else 0 end) as this_month', [now()->startOfMonth()])
            ->groupBy('currency')
            ->get();

        $revenue = [];

        foreach ($rows as $row) {
            $revenue[$row->currency] = [
                'this_month' => (int) $row->this_month,
                'all_time' => (int) $row->all_time,
            ];
        }

        return $revenue;
    }
}
