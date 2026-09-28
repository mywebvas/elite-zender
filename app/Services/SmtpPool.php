<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\SmtpAccount;
use Illuminate\Support\Collection;

/**
 * Health-weighted SMTP relay rotation (docs/08-MIGRATION-PLAN.md).
 *
 * The legacy behaviour was a naive `$i % count` round-robin that ignored
 * `daily_limit`, `health_score` and `status`. That happily kept hammering a
 * relay that had already blown through its quota — the fastest way to get a
 * sending domain blocklisted.
 *
 * Selection is deterministic (no randomness) so a chunk can be replayed after
 * a worker crash and produce the same distribution:
 *
 *  1. Only `active` relays with quota left are eligible.
 *  2. Relays are ordered by health, then remaining quota, then id.
 *  3. Each relay is handed out up to `weight` times per cycle, where weight is
 *     proportional to its health score — healthier relays carry more load.
 */
final class SmtpPool
{
    /** @var list<SmtpAccount> */
    private array $rotation = [];

    private int $cursor = 0;

    /** @var array<string, int> */
    private array $reserved = [];

    /**
     * @param  Collection<int, SmtpAccount>  $accounts
     */
    public function __construct(Collection $accounts)
    {
        $eligible = $accounts
            ->filter(fn (SmtpAccount $a) => $a->status === SmtpAccount::STATUS_ACTIVE)
            ->filter(fn (SmtpAccount $a) => $a->remainingQuotaToday() > 0)
            ->sortBy([
                fn (SmtpAccount $a, SmtpAccount $b) => $b->health_score <=> $a->health_score,
                fn (SmtpAccount $a, SmtpAccount $b) => $b->remainingQuotaToday() <=> $a->remainingQuotaToday(),
                fn (SmtpAccount $a, SmtpAccount $b) => strcmp((string) $a->getKey(), (string) $b->getKey()),
            ])
            ->values();

        $this->rotation = $this->buildRotation($eligible);
    }

    /** Build the pool for a campaign, falling back to the tenant's relays. */
    public static function forCampaign(Campaign $campaign): self
    {
        $accounts = $campaign->smtpAccounts()->get();

        if ($accounts->isEmpty()) {
            $accounts = SmtpAccount::sendable()->get();
        }

        return new self($accounts);
    }

    public function isEmpty(): bool
    {
        return $this->rotation === [];
    }

    public function count(): int
    {
        return count(array_unique(array_map(
            static fn (SmtpAccount $a) => (string) $a->getKey(),
            $this->rotation,
        )));
    }

    /**
     * Hand out the next relay that still has quota, or null when the whole
     * pool is exhausted for today.
     */
    public function next(): ?SmtpAccount
    {
        $attempts = count($this->rotation);

        while ($attempts-- > 0) {
            $account = $this->rotation[$this->cursor % count($this->rotation)];
            $this->cursor++;

            $key = (string) $account->getKey();
            $used = $this->reserved[$key] ?? 0;

            if ($used < $account->remainingQuotaToday()) {
                $this->reserved[$key] = $used + 1;

                return $account;
            }
        }

        return null;
    }

    /**
     * Flush the sends reserved during this chunk back to the database with an
     * atomic increment, so concurrent workers cannot overshoot the cap.
     */
    public function commitUsage(): void
    {
        foreach ($this->reserved as $id => $count) {
            if ($count > 0) {
                SmtpAccount::withoutGlobalScopes()
                    ->whereKey($id)
                    ->increment('sent_today', $count);
            }
        }

        $this->reserved = [];
    }

    /**
     * Expand accounts into a weighted ring. Health 100 → 4 slots, 75 → 3, and
     * so on; every eligible relay keeps at least one slot.
     *
     * @param  Collection<int, SmtpAccount>  $accounts
     * @return list<SmtpAccount>
     */
    private function buildRotation(Collection $accounts): array
    {
        $weights = $accounts->map(fn (SmtpAccount $a) => max(1, (int) ceil($a->health_score / 25)));

        // When every relay is equally healthy, a weight of 1 keeps the ring a
        // plain round-robin — the simplest behaviour an operator can predict.
        if ($weights->unique()->count() === 1) {
            return $accounts->all();
        }

        $ring = [];

        foreach ($accounts as $position => $account) {
            $ring = array_merge($ring, array_fill(0, $weights[$position], $account));
        }

        return $ring;
    }
}
