<?php

namespace App\Models;

use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only engagement event (open / click / bounce / complaint).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $campaign_id
 * @property string $contact_id
 * @property string $type
 * @property string|null $url
 */
class CampaignEvent extends Model
{
    use HasTenant, HasUuid7;

    public const TYPE_OPEN = 'open';

    public const TYPE_CLICK = 'click';

    public const TYPE_BOUNCE = 'bounce';

    public const TYPE_COMPLAINT = 'complaint';

    public const TYPE_UNSUBSCRIBE = 'unsubscribe';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_OPEN,
        self::TYPE_CLICK,
        self::TYPE_BOUNCE,
        self::TYPE_COMPLAINT,
        self::TYPE_UNSUBSCRIBE,
    ];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'contact_id',
        'type',
        'url',
        'ip_address',
        'user_agent',
    ];

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
