<?php

namespace App\Models;

use App\Tenancy\Auditable;
use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tenant's outbound SMTP relay.
 *
 * The password column is encrypted by the `encrypted` cast — never encrypt it
 * again at the call site or the relay will be handed ciphertext.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $host
 * @property int $port
 * @property string|null $username
 * @property string|null $password
 * @property string $encryption
 * @property string $from_email
 * @property string $from_name
 * @property int $daily_limit
 * @property int $sent_today
 * @property int $health_score
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> sendable()
 */
class SmtpAccount extends Model
{
    /** @use HasFactory<\Database\Factories\SmtpAccountFactory> */
    use Auditable, HasFactory, HasTenant, HasUuid7, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ERROR = 'error';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'name', 'host', 'port', 'username', 'password',
        'encryption', 'from_email', 'from_name', 'daily_limit', 'sent_today',
        'health_score', 'status', 'last_checked_at',
    ];

    /** @var list<string> */
    protected $hidden = ['password'];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relays that may still send right now: active and under their daily cap.
     *
     * @param  Builder<SmtpAccount>  $query
     * @return Builder<SmtpAccount>
     */
    public function scopeSendable(Builder $query): Builder
    {
        $query->where('status', self::STATUS_ACTIVE);
        $query->whereColumn('sent_today', '<', 'daily_limit');

        return $query;
    }

    /** Remaining sends before this relay hits its configured daily cap. */
    public function remainingQuotaToday(): int
    {
        return max(0, $this->daily_limit - $this->sent_today);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'daily_limit' => 'integer',
            'sent_today' => 'integer',
            'health_score' => 'integer',
            'last_checked_at' => 'datetime',
            'password' => 'encrypted',
        ];
    }
}
