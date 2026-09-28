<?php

namespace App\Models;

use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit record for "log in as this customer".
 *
 * Impersonation is the single most sensitive capability in the platform, so it
 * is never silent: every session is recorded before it starts and closed when
 * it ends, and the customer-facing UI shows a persistent banner throughout.
 *
 * @property string $id
 * @property string $admin_id
 * @property string $tenant_id
 * @property string $user_id
 */
class AdminImpersonation extends Model
{
    use HasUuid7;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'admin_id', 'tenant_id', 'user_id', 'reason', 'ip_address', 'started_at', 'ended_at',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }
}
