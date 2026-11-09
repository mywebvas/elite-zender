<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactAutomation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
        // Purge legacy key if present to prevent incomplete class serialization issues
        if (Cache::has('admin_dashboard_kpis')) {
            Cache::forget('admin_dashboard_kpis');
        }

        /** @var array{stats: array<string, int>, revenue: array<string, array{this_month: int, all_time: int}>, subscriptions: array<string, int>|Collection<string, int>, planBreakdown: array<string, int>|Collection<string, int>} $kpis */
        $kpis = app()->environment('testing')
            ? $this->calculateKpis()
            : Cache::remember('admin_dashboard_kpis_v2', 30, fn () => $this->calculateKpis());

        // Defensive normalization: always ensure pure Collection instances for the view
        $rawSubs = $kpis['subscriptions'] ?? [];
        $subscriptions = $rawSubs instanceof Collection ? $rawSubs : collect(is_array($rawSubs) ? $rawSubs : []);

        $rawPlans = $kpis['planBreakdown'] ?? [];
        $planBreakdown = $rawPlans instanceof Collection ? $rawPlans : collect(is_array($rawPlans) ? $rawPlans : []);

        return view('admin.dashboard', [
            'stats' => $kpis['stats'],
            'revenue' => $kpis['revenue'],
            'subscriptions' => $subscriptions,
            'planBreakdown' => $planBreakdown,
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

            // The three things that mean "go and look at something now".
            'attention' => [
                'failed_jobs' => DB::table('failed_jobs')->count(),
                'paused_automations' => ContactAutomation::withoutGlobalScopes()
                    ->where('status', ContactAutomation::STATUS_PAUSED)->count(),
                'suspended_tenants' => Tenant::query()->where('status', Tenant::STATUS_SUSPENDED)->count(),
            ],
            'recentActivity' => AdminActivity::with('tenant:id,name')->latest('created_at')->limit(8)->get(),
        ]);
    }

    /**
     * Expensive platform aggregates, cached for 30 seconds in production.
     * Caches raw primitive arrays rather than Collection objects to prevent
     * incomplete class deserialization errors in persistent cache drivers.
     *
     * @return array{stats: array<string, int>, revenue: array<string, array{this_month: int, all_time: int}>, subscriptions: array<string, int>, planBreakdown: array<string, int>}
     */
    private function calculateKpis(): array
    {
        return [
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
                ->pluck('aggregate', 'status')
                ->all(),
            'planBreakdown' => DB::table('subscriptions')
                ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->selectRaw('plans.name, count(*) as aggregate')
                ->groupBy('plans.name')
                ->orderByDesc('aggregate')
                ->pluck('aggregate', 'plans.name')
                ->all(),
        ];
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
