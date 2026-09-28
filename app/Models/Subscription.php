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
 */
class Subscription extends Model
{
    use HasTenant, HasUuid7;

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_EXPIRED = 'expired';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'plan_id', 'status', 'currency', 'amount', 'interval',
        'trial_ends_at', 'current_period_start', 'current_period_end',
        'cancel_at', 'canceled_at', 'gateway', 'gateway_ref',
    ];

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
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
        ];
    }
}
