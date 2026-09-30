<?php

namespace App\Models;

use App\Tenancy\Auditable;
use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A pending invitation into a workspace.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $email
 * @property string $role
 * @property string $token_hash
 * @property string|null $invited_by
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 *
 * @method static Builder<static> pending()
 */
class Invitation extends Model
{
    use Auditable, HasTenant, HasUuid7;

    /** How long a link stays usable. Long enough for a holiday, short enough to matter. */
    public const TTL_DAYS = 7;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'email', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at'];

    /**
     * The token is never stored.
     *
     * Only its SHA-256 lands in the database, exactly as API keys are handled:
     * a leaked backup must not be a working invitation into a customer's
     * workspace. The plaintext exists for the length of one request and then
     * only inside the email.
     */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }

    /** @param Builder<Invitation> $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
