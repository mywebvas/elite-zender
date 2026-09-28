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

    /**
     * Human labels for the builder.
     *
     * The <select> used to be hand-written in the Blade template and had
     * drifted: it offered `link_clicked` and `page_visited`, neither of which
     * the server accepts, so choosing either failed validation and the form
     * appeared to do nothing. Labels now come from here, so the UI cannot
     * offer a trigger the backend rejects.
     *
     * @return array<string, string>
     */
    public static function triggerLabels(): array
    {
        return [
            self::TRIGGER_SUBSCRIBED => 'When a contact subscribes',
            self::TRIGGER_TAG_ADDED => 'When a tag is added to a contact',
            self::TRIGGER_LIST_JOINED => 'When a contact joins a list',
            self::TRIGGER_CAMPAIGN_OPENED => 'When a contact opens a campaign',
            self::TRIGGER_CAMPAIGN_CLICKED => 'When a contact clicks a link in a campaign',
        ];
    }

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
