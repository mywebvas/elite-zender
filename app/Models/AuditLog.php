<?php

namespace App\Models;

use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only audit log record.
 *
 * Security constraints (docs/06-SECURITY-COMPLIANCE.md §1):
 *   - No update() or delete() allowed — immutable after write
 *   - 24-month retention; archival to B2 handled by scheduler (M7)
 *   - DB-level: no DELETE grants for the app DB role (production)
 *
 * Written exclusively by App\Tenancy\Auditable trait hooks.
 */
class AuditLog extends Model
{
    use HasUuid7;

    /** Audit logs are never updated — only ever created. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The model that was acted upon (Campaign, SmtpAccount, User, …).
     *
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // -------------------------------------------------------------------------
    // Guard: refuse mutations after creation
    // -------------------------------------------------------------------------

    /**
     * @throws LogicException
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('AuditLog records are immutable.');
    }

    /**
     * @throws LogicException
     */
    public function delete(): ?bool
    {
        throw new LogicException('AuditLog records cannot be deleted.');
    }
}
