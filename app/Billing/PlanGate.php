<?php

namespace App\Billing;

use App\Models\Contact;
use App\Models\SmtpAccount;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Plan limits.
 *
 * A `null` limit means unlimited and must never be confused with 0, which means
 * "none allowed" — conflating the two is how Enterprise customers get locked
 * out of their own account.
 *
 * Workspaces with no subscription row at all are treated as free-tier rather
 * than blocked: an account created before billing existed, or one whose
 * subscription failed to write, should degrade to limited, not to broken.
 */
final class PlanGate
{
    public const METRIC_EMAILS = 'emails_sent';

    /** Remaining headroom, or null when unlimited. */
    public function remaining(Tenant $tenant, string $limit): ?int
    {
        $max = $this->limitFor($tenant, $limit);

        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->usage($tenant, $limit));
    }

    public function allows(Tenant $tenant, string $limit, int $additional = 1): bool
    {
        $remaining = $this->remaining($tenant, $limit);

        return $remaining === null || $remaining >= $additional;
    }

    /** Human-readable refusal, or null when the action is permitted. */
    public function denialReason(Tenant $tenant, string $limit, int $additional = 1): ?string
    {
        if ($this->allows($tenant, $limit, $additional)) {
            return null;
        }

        $max = $this->limitFor($tenant, $limit);
        $label = str_replace('_', ' ', $limit);

        return "Your plan allows {$max} {$label}. Upgrade to add more.";
    }

    public function limitFor(Tenant $tenant, string $limit): ?int
    {
        return $this->planFor($tenant)?->limit($limit);
    }

    public function usage(Tenant $tenant, string $limit): int
    {
        return match ($limit) {
            'contacts' => Contact::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->whereNull('deleted_at')->count(),
            'smtp_accounts' => SmtpAccount::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->whereNull('deleted_at')->count(),
            'users' => User::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->count(),
            'emails_per_month' => $this->monthlyEmails($tenant),
            default => 0,
        };
    }

    /**
     * A usage snapshot for the billing page.
     *
     * @return array<string, array{used: int, limit: int|null, percent: int|null}>
     */
    public function snapshot(Tenant $tenant): array
    {
        $snapshot = [];

        foreach (['contacts', 'emails_per_month', 'smtp_accounts', 'users'] as $limit) {
            $max = $this->limitFor($tenant, $limit);
            $used = $this->usage($tenant, $limit);

            $snapshot[$limit] = [
                'used' => $used,
                'limit' => $max,
                'percent' => $max === null || $max === 0 ? null : min(100, (int) round($used / $max * 100)),
            ];
        }

        return $snapshot;
    }

    /** Is the workspace entitled to send at all? */
    public function canSend(Tenant $tenant): bool
    {
        $subscription = $this->subscriptionFor($tenant);

        if ($subscription !== null && ! $subscription->isUsable()) {
            return false;
        }

        return $this->allows($tenant, 'emails_per_month');
    }

    /** Atomic monthly counter increment, called by the send workers. */
    public function recordEmailsSent(Tenant|string $tenant, int $count): void
    {
        if ($count < 1) {
            return;
        }

        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        DB::table('usage_counters')->upsert(
            [[
                'id' => (string) \Illuminate\Support\Str::uuid7(),
                'tenant_id' => $tenantId,
                'metric' => self::METRIC_EMAILS,
                'period' => now()->format('Y-m'),
                'value' => $count,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['tenant_id', 'metric', 'period'],
            // Concurrent chunk workers must accumulate, not overwrite.
            ['value' => DB::raw('usage_counters.value + '.$count), 'updated_at' => now()],
        );
    }

    public function subscriptionFor(Tenant $tenant): ?Subscription
    {
        return Subscription::withoutGlobalScopes()
            ->with('plan')
            ->firstWhere('tenant_id', $tenant->getKey());
    }

    private function planFor(Tenant $tenant): ?\App\Models\Plan
    {
        $subscription = $this->subscriptionFor($tenant);

        if ($subscription?->plan !== null) {
            return $subscription->plan;
        }

        // No subscription yet: fall back to the free tier's limits rather than
        // granting unlimited access by accident.
        return \App\Models\Plan::query()->where('code', 'free')->first();
    }

    private function monthlyEmails(Tenant $tenant): int
    {
        return (int) DB::table('usage_counters')
            ->where('tenant_id', $tenant->getKey())
            ->where('metric', self::METRIC_EMAILS)
            ->where('period', now()->format('Y-m'))
            ->value('value');
    }
}
