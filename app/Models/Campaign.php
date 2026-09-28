<?php

namespace App\Models;

use App\Tenancy\Auditable;
use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Email campaign. Tenant-scoped.
 */
class Campaign extends Model
{
    use Auditable, HasFactory, HasTenant, HasUuid7, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'subject', 'body_html', 'body_text',
        'status', 'list_id', 'scheduled_at', 'settings', 'stats_cache',
    ];

    public function smtpAccounts(): BelongsToMany
    {
        return $this->belongsToMany(SmtpAccount::class);
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CampaignEvent::class);
    }

    public function list(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ContactList::class, 'list_id');
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'settings' => 'array',
            'stats_cache' => 'array',
        ];
    }
}
