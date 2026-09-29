<?php

namespace App\Models;

use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workspace's current plan commitment.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $plan_id
 * @property string $status
 * @property string $currency
 * @property int $amount
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $current_period_end
 * @property string|null $gateway_token
 * @property string|null $gateway_customer
 * @property int $dunning_attempts
 * @property string|null $cancellation_reason
 * @property \Illuminate\Support\Carbon|null $downgraded_at
 */
class Subscription extends Model
{
    use HasTenant, HasUuid7;

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_EXPIRED = 'expired';

    /**
     * Why a customer left, in their own words where they give them.
     *
     * A fixed vocabulary rather than free text alone: free text cannot be
     * counted, and a churn reason you cannot count is a churn reason nobody
     * ever acts on. The optional note sits alongside it.
     *
     * @var array<string, string>
     */
    public const CANCELLATION_REASONS = [
        'too_expensive' => 'Too expensive for what we send',
        'missing_feature' => 'Missing a feature we need',
        'too_complex' => 'Harder to use than I expected',
        'deliverability' => 'Deliverability was not good enough',
        'switching' => 'Moving to another tool',
        'not_sending' => 'We are not sending email right now',
        'temporary' => 'Just pausing for a while',
        'other' => 'Something else',
    ];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'plan_id', 'pending_plan_id', 'status', 'currency', 'amount', 'interval',
        'trial_ends_at', 'current_period_start', 'current_period_end',
        'cancel_at', 'canceled_at', 'cancellation_reason', 'cancellation_feedback',
        'downgraded_at', 'gateway', 'gateway_ref',
        'gateway_customer', 'gateway_token', 'card_brand', 'card_last_four',
        'card_exp_month', 'card_exp_year',
        'dunning_attempts', 'next_retry_at',
    ];

    /** Bearer credentials against the customer's card — never serialise them. */
    protected $hidden = ['gateway_token', 'gateway_customer'];

    /**
     * Keep the request-scoped plan memo honest.
     *
     * `PlanGate` caches the resolved subscription for the life of a request or
     * a queued job. Anything that writes this row — an upgrade, a settled
     * invoice, a lapse — has to invalidate that memo, or the rest of the
     * request keeps enforcing the plan the customer just left.
     */
    protected static function booted(): void
    {
        $flush = static fn () => app(\App\Billing\PlanGate::class)->flush();

        static::saved($flush);
        static::deleted($flush);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The plan a scheduled downgrade will apply at period end.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    /**
     * The plan's display name, or a fallback.
     *
     * `plan_id` is nullable and a plan can be archived out from under a
     * subscription, so every caller needs the same defensive read — and
     * having it in one place stops half of them forgetting.
     */
    public function planName(string $fallback = 'current'): string
    {
        $plan = $this->plan;

        // An explicit type check rather than `?->name ?? $fallback`: the
        // relation is `mixed` to a Larastan-less analyser, and this states
        // the actual contract — a Plan, or nothing.
        return $plan instanceof Plan ? (string) $plan->name : $fallback;
    }

    /**
     * The last moment the saved card still works, or null when we do not
     * know. A card is valid through the final day of its expiry month.
     */
    public function cardExpiresAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->card_exp_month === null || $this->card_exp_year === null) {
            return null;
        }

        return \Illuminate\Support\Carbon::createFromDate(
            (int) $this->card_exp_year,
            (int) $this->card_exp_month,
            1,
        )->endOfMonth();
    }

    /** Can this subscription renew itself without the customer returning? */
    public function canAutoRenew(): bool
    {
        return filled($this->gateway_token)
            && $this->canceled_at === null
            && $this->amount > 0;
    }

    /** Is a cancellation scheduled but not yet in effect? */
    public function isEnding(): bool
    {
        return $this->canceled_at !== null
            && ($this->cancel_at === null || $this->cancel_at->isFuture());
    }

    public function isDue(): bool
    {
        return $this->current_period_end !== null && $this->current_period_end->isPast();
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Is the workspace entitled to use the product right now?
     *
     * Past-due keeps working through the grace window — cutting a paying
     * customer off the hour a card expires is how you lose them.
     */
    public function isUsable(): bool
    {
        if (in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE], true)) {
            return true;
        }

        if ($this->status === self::STATUS_PAST_DUE) {
            $deadline = ($this->current_period_end ?? $this->updated_at)
                ?->addDays((int) config('billing.grace_days', 7));

            return $deadline === null || $deadline->isFuture();
        }

        return false;
    }

    public function onTrial(): bool
    {
        return $this->status === self::STATUS_TRIALING
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function daysUntilRenewal(): ?int
    {
        $days = $this->current_period_end?->diffInDays(now(), absolute: false);

        return $days === null ? null : (int) $days;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at' => 'datetime',
            'canceled_at' => 'datetime',
            'downgraded_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'dunning_attempts' => 'integer',
            // Encrypted at rest: a leaked database row must not be a means of
            // charging the customer's card.
            'gateway_token' => 'encrypted',
            'gateway_customer' => 'encrypted',
        ];
    }
}
