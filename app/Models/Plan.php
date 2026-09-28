<?php

namespace App\Models;

use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A billable tier. Not tenant-scoped — plans are platform-wide.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property int|null $price_ngn minor units (kobo)
 * @property int|null $price_usd minor units (cents)
 * @property array<string, int|null>|null $limits
 * @property bool $is_active
 * @property bool $is_public
 *
 * @method static Builder<static> active()
 * @method static Builder<static> public()
 * @method static Builder<static> query()
 * @method static Builder<static> create(array<string, mixed> $attributes)
 */
class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory, HasUuid7;

    /** @var list<string> */
    protected $fillable = [
        'code', 'name', 'description', 'price_ngn', 'price_usd', 'interval',
        'limits', 'features', 'is_active', 'is_public', 'sort_order',
    ];

    /** A quoted plan has no self-service price. */
    public function isQuoteOnly(): bool
    {
        return $this->price_ngn === null && $this->price_usd === null;
    }

    public function isFree(): bool
    {
        return $this->price_ngn === 0 && $this->price_usd === 0;
    }

    /** Price in minor units for a currency, or null when quote-only. */
    public function priceFor(string $currency): ?int
    {
        return match (strtoupper($currency)) {
            'NGN' => $this->price_ngn,
            default => $this->price_usd,
        };
    }

    /**
     * A limit value. `null` means unlimited — callers must distinguish that
     * from 0, which means "none allowed".
     */
    public function limit(string $key): ?int
    {
        $value = ($this->limits ?? [])[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopeActive(Builder $query): Builder
    {
        $query->where('is_active', true);

        return $query;
    }

    /**
     * Plans a customer may self-serve: listed publicly *and* still sold.
     *
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopePublic(Builder $query): Builder
    {
        $query->where('is_public', true)->where('is_active', true);

        return $query;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_ngn' => 'integer',
            'price_usd' => 'integer',
            'limits' => 'array',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
