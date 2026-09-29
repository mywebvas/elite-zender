<?php

namespace App\Models;

use App\Tenancy\Auditable;
use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A right-of-access or right-to-erasure request.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $type
 * @property string $status
 * @property string|null $file_path
 * @property int|null $file_size
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $scheduled_for
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class DataRequest extends Model
{
    use Auditable, HasTenant, HasUuid7;

    public const TYPE_EXPORT = 'export';

    public const TYPE_DELETION = 'deletion';

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    /** How long a finished export stays downloadable before it is shredded. */
    public const EXPORT_TTL_DAYS = 7;

    /**
     * The cooling-off window on a deletion.
     *
     * Long enough that an angry Friday click can be undone on Monday, short
     * enough that it is a real answer to an erasure request rather than a
     * delaying tactic.
     */
    public const DELETION_GRACE_DAYS = 7;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'requested_by', 'type', 'status', 'reason', 'note',
        'file_path', 'file_size', 'expires_at', 'scheduled_for', 'completed_at',
    ];

    public function isDownloadable(): bool
    {
        return $this->type === self::TYPE_EXPORT
            && $this->status === self::STATUS_READY
            && $this->file_path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isCancellable(): bool
    {
        return $this->type === self::TYPE_DELETION && $this->status === self::STATUS_PENDING;
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'expires_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
