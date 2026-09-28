<?php

namespace App\Models;

use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only payment ledger. A refund is a new row with a negative amount,
 * never an edit of the original — that is what makes the history auditable.
 *
 * @property string $id
 * @property string $gateway
 * @property string $status
 * @property int $amount minor units; negative for refunds
 */
class Payment extends Model
{
    use HasTenant, HasUuid7;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'invoice_id', 'gateway', 'reference', 'gateway_ref',
        'status', 'currency', 'amount', 'proof_path', 'failure_reason',
        'paid_at', 'reviewed_by', 'reviewed_at', 'payload',
    ];

    /** Raw gateway payloads can carry cardholder metadata — never serialise them. */
    protected $hidden = ['payload'];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function isAwaitingReview(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->gateway === 'manual';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
