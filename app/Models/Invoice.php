<?php

namespace App\Models;

use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $number
 * @property string $status
 * @property string $currency
 * @property int $total minor units
 * @property int $amount_paid minor units
 */
class Invoice extends Model
{
    use HasTenant, HasUuid7;

    public const STATUS_OPEN = 'open';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    public const STATUS_UNCOLLECTIBLE = 'uncollectible';

    public const REASON_MANUAL = 'manual';

    public const REASON_RENEWAL = 'renewal';

    public const REASON_UPGRADE = 'upgrade';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'subscription_id', 'plan_id', 'number', 'status', 'reason',
        'currency', 'subtotal', 'tax', 'total', 'amount_paid',
        'period_start', 'period_end', 'due_at', 'paid_at', 'voided_at',
        'line_items', 'metadata',
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** The plan's display name, or a fallback. See Subscription::planName(). */
    public function planName(string $fallback = 'your plan'): string
    {
        $plan = $this->plan;

        // An explicit type check rather than `?->name ?? $fallback`: the
        // relation is `mixed` to a Larastan-less analyser, and this states
        // the actual contract — a Plan, or nothing.
        return $plan instanceof Plan ? (string) $plan->name : $fallback;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPayable(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->balance() > 0;
    }

    /** Outstanding amount in minor units. */
    public function balance(): int
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OPEN
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    /** Sequential, human-quotable, and unique — finance teams ask for these. */
    public static function nextNumber(): string
    {
        $prefix = 'EZ-'.now()->format('Ym');

        $latest = static::withoutGlobalScopes()
            ->where('number', 'like', $prefix.'-%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $latest === null ? 0 : (int) substr((string) $latest, -5);

        return sprintf('%s-%05d', $prefix, $sequence + 1);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'line_items' => 'array',
            'metadata' => 'array',
        ];
    }
}
