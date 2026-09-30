<?php

namespace App\Models;

use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One lifecycle email, recorded so it can never be sent twice.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $key
 * @property string $type
 * @property int $recipients
 * @property \Illuminate\Support\Carbon $sent_at
 */
class LifecycleMessage extends Model
{
    use HasTenant, HasUuid7;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'key', 'type', 'recipients', 'sent_at'];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'recipients' => 'integer',
        ];
    }
}
