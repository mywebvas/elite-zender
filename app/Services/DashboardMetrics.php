<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Contact;
use App\Models\SmtpAccount;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read model for the dashboard.
 *
 * Every figure is derived from real tables and cached briefly — the dashboard
 * is the most-hit authenticated page, and these are aggregate scans.
 */
final class DashboardMetrics
{
    private const CACHE_TTL_SECONDS = 60;

    /**
     * @return array{
     *     sent: int, opens: int, clicks: int, bounces: int,
     *     open_rate: float, click_rate: float, bounce_rate: float,
     *     contacts: int, unsubscribed: int
     * }
     */
    public function kpis(): array
    {
        return Cache::remember($this->cacheKey('kpis'), self::CACHE_TTL_SECONDS, function (): array {
            $counts = CampaignEvent::query()
                ->selectRaw('type, count(*) as aggregate')
                ->groupBy('type')
                ->pluck('aggregate', 'type');

            $opens = (int) ($counts[CampaignEvent::TYPE_OPEN] ?? 0);
            $clicks = (int) ($counts[CampaignEvent::TYPE_CLICK] ?? 0);
            $bounces = (int) ($counts[CampaignEvent::TYPE_BOUNCE] ?? 0);

            $sent = (int) Campaign::query()->sum('sent_count');
            $contacts = Contact::query()->count();
            $unsubscribed = Contact::query()->where('status', Contact::STATUS_UNSUBSCRIBED)->count();

            $denominator = max($sent, 1);

            return [
                'sent' => $sent,
                'opens' => $opens,
                'clicks' => $clicks,
                'bounces' => $bounces,
                'open_rate' => $sent > 0 ? round($opens / $denominator * 100, 1) : 0.0,
                'click_rate' => $sent > 0 ? round($clicks / $denominator * 100, 1) : 0.0,
                'bounce_rate' => $sent > 0 ? round($bounces / $denominator * 100, 1) : 0.0,
                'contacts' => $contacts,
                'unsubscribed' => $unsubscribed,
            ];
        });
    }

    /** @return Collection<int, Campaign> */
    public function recentCampaigns(int $limit = 5): Collection
    {
        return Campaign::query()
            ->with('list:id,name')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, SmtpAccount> */
    public function smtpHealth(): Collection
    {
        return SmtpAccount::query()
            ->orderByDesc('health_score')
            ->limit(5)
            ->get();
    }

    private function cacheKey(string $suffix): string
    {
        return 'dashboard:'.(TenantContext::id() ?? 'none').':'.$suffix;
    }
}
