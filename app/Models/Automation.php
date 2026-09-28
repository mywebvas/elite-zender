<?php

namespace App\Models;

use App\Tenancy\Auditable;
use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A trigger-driven sequence of steps executed against contacts. Tenant-scoped.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $trigger_type
 * @property array<string, mixed>|null $trigger_config
 * @property bool $is_active
 */
class Automation extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use Auditable, HasFactory, HasTenant, HasUuid7, SoftDeletes;

    public const TRIGGER_SUBSCRIBED = 'subscribed';

    public const TRIGGER_TAG_ADDED = 'tag_added';

    public const TRIGGER_LIST_JOINED = 'list_joined';

    public const TRIGGER_CAMPAIGN_OPENED = 'campaign_opened';

    public const TRIGGER_CAMPAIGN_CLICKED = 'campaign_clicked';

    /** @var list<string> */
    public const TRIGGERS = [
        self::TRIGGER_SUBSCRIBED,
        self::TRIGGER_TAG_ADDED,
        self::TRIGGER_LIST_JOINED,
        self::TRIGGER_CAMPAIGN_OPENED,
        self::TRIGGER_CAMPAIGN_CLICKED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'name',
        'trigger_type',
        'trigger_config',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<AutomationStep, $this> */
    public function steps(): HasMany
    {
        $relation = $this->hasMany(AutomationStep::class);
        $relation->orderBy('order_index');

        return $relation;
    }
}
