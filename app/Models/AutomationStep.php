<?php

namespace App\Models;

use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single node in an automation graph.
 *
 * Tenant isolation is inherited from the parent automation (steps are only ever
 * reachable through an already tenant-scoped Automation), so no global scope of
 * its own is needed.
 *
 * @property string $id
 * @property string $automation_id
 * @property string|null $parent_step_id
 * @property string $type
 * @property array<string, mixed>|null $config
 * @property int $order_index
 */
class AutomationStep extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory, HasUuid7;

    public const TYPE_SEND_EMAIL = 'send_email';

    public const TYPE_WAIT = 'wait';

    public const TYPE_ADD_TAG = 'add_tag';

    public const TYPE_REMOVE_TAG = 'remove_tag';

    public const TYPE_UPDATE_FIELD = 'update_field';

    public const TYPE_WEBHOOK = 'webhook';

    public const TYPE_CONDITION = 'condition';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_SEND_EMAIL,
        self::TYPE_WAIT,
        self::TYPE_ADD_TAG,
        self::TYPE_REMOVE_TAG,
        self::TYPE_UPDATE_FIELD,
        self::TYPE_WEBHOOK,
        self::TYPE_CONDITION,
    ];

    /** @var list<string> */
    protected $fillable = [
        'automation_id',
        'parent_step_id',
        'type',
        'config',
        'order_index',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'order_index' => 'integer',
        ];
    }

    /** @return BelongsTo<Automation, $this> */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }
}
