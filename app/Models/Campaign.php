<?php

namespace App\Models;

use App\Tenancy\Auditable;
use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Email campaign. Tenant-scoped.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $subject
 * @property string|null $body_html
 * @property string|null $body_text
 * @property string $status
 * @property string|null $list_id
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $stats_cache
 * @property int $recipients_count
 * @property int $sent_count
 * @property int $failed_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Campaign extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignFactory> */
    use Auditable, HasFactory, HasTenant, HasUuid7, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_QUEUED,
        self::STATUS_SENDING,
        self::STATUS_PAUSED,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'name', 'subject', 'body_html', 'body_text',
        'status', 'list_id', 'scheduled_at', 'settings', 'stats_cache',
        'recipients_count', 'sent_count', 'failed_count', 'started_at', 'completed_at',
    ];

    /** @return BelongsToMany<SmtpAccount, $this> */
    public function smtpAccounts(): BelongsToMany
    {
        return $this->belongsToMany(SmtpAccount::class);
    }

    /** @return HasMany<CampaignEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(CampaignEvent::class);
    }

    /** @return BelongsTo<ContactList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ContactList::class, 'list_id');
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Only drafts may be mutated — once queued the content is frozen. */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** Campaign id whose openers should be skipped (smart retargeting). */
    public function excludedOpenersCampaignId(): ?string
    {
        $value = $this->settings['exclude_openers_of'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'recipients_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'settings' => 'array',
            'stats_cache' => 'array',
        ];
    }
}
