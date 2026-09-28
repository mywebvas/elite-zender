<?php

namespace App\Models;

use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only record of what a platform operator did.
 *
 * Deliberately denormalises the operator's email: the answer to "who suspended
 * this customer?" must survive that operator's account being deleted.
 *
 * @property string $id
 * @property string $action
 * @property string $severity
 *
 * @method static Builder<static> critical()
 */
class AdminActivity extends Model
{
    use HasUuid7;

    protected $table = 'admin_activity_log';

    public const UPDATED_AT = null;

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_NOTICE = 'notice';

    public const SEVERITY_CRITICAL = 'critical';

    /** @var list<string> */
    protected $fillable = [
        'admin_id', 'admin_email', 'action', 'description', 'subject_type', 'subject_id',
        'tenant_id', 'severity', 'reason', 'changes', 'ip_address', 'user_agent',
    ];

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<AdminActivity>  $query
     * @return Builder<AdminActivity>
     */
    public function scopeCritical(Builder $query): Builder
    {
        $query->where('severity', self::SEVERITY_CRITICAL);

        return $query;
    }

    /** An audit trail that can be edited is not an audit trail. */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Admin activity records are immutable.');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }
}
