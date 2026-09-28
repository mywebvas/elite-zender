<?php

namespace App\Models;

use App\Tenancy\HasUuid7;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A platform-level API key for the operator API.
 *
 * The key itself is never stored — only a SHA-256 of it, plus a short prefix so
 * a human can tell two keys apart in a list. A database dump therefore cannot
 * be replayed as API access, and a key can only ever be shown once, at the
 * moment it is created.
 *
 * @property string $id
 * @property string $name
 * @property string $prefix
 * @property list<string>|null $abilities
 *
 * @method static Builder<static> usable()
 * @method static Builder<static> query()
 */
class ApiKey extends Model
{
    use HasUuid7;

    /** Capabilities a platform key can be granted. */
    public const ABILITIES = [
        'tenants:read' => 'Read workspaces',
        'tenants:write' => 'Suspend, reinstate and change plans',
        'billing:read' => 'Read invoices and payments',
        'billing:write' => 'Void invoices and confirm transfers',
        'metrics:read' => 'Read platform metrics',
    ];

    /** @var list<string> */
    protected $fillable = [
        'name', 'created_by', 'prefix', 'hash', 'abilities',
        'allowed_ips', 'expires_at', 'revoked_at',
    ];

    protected $hidden = ['hash'];

    /** @return BelongsTo<Admin, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Mint a new key.
     *
     * Returns the plaintext alongside the record because this is the only
     * moment it will ever exist in readable form.
     *
     * @param  list<string>  $abilities
     * @param  list<string>  $allowedIps
     * @return array{0: self, 1: string}
     */
    public static function mint(
        string $name,
        array $abilities,
        ?string $createdBy = null,
        array $allowedIps = [],
        ?DateTimeInterface $expiresAt = null,
    ): array {
        // `ez_live_` makes a leaked key greppable in a codebase or a log, which
        // is exactly why secret scanners look for vendor prefixes.
        $plain = 'ez_live_'.Str::random(48);

        $key = static::create([
            'name' => $name,
            'created_by' => $createdBy,
            'prefix' => substr($plain, 0, 12),
            'hash' => hash('sha256', $plain),
            'abilities' => $abilities,
            'allowed_ips' => $allowedIps ?: null,
            'expires_at' => $expiresAt,
        ]);

        return [$key, $plain];
    }

    /** Resolve a presented key, or null when it is unknown, expired or revoked. */
    public static function resolve(string $plain): ?self
    {
        return static::usable()->firstWhere('hash', hash('sha256', $plain));
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true);
    }

    /** Does the caller's address satisfy the allow-list, if one is set? */
    public function allowsIp(?string $ip): bool
    {
        $allowed = $this->allowed_ips ?? [];

        return $allowed === [] || ($ip !== null && in_array($ip, $allowed, true));
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    public function status(): string
    {
        return match (true) {
            $this->revoked_at !== null => 'revoked',
            $this->expires_at !== null && $this->expires_at->isPast() => 'expired',
            default => 'active',
        };
    }

    /**
     * @param  Builder<ApiKey>  $query
     * @return Builder<ApiKey>
     */
    public function scopeUsable(Builder $query): Builder
    {
        $query->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        return $query;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'allowed_ips' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
