<?php

namespace App\Models;

use App\Tenancy\Auditable;
use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A subscriber inside a workspace.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $email
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $status
 * @property array<string, mixed>|null $custom_fields
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> mailable()
 * @method static \Illuminate\Database\Eloquent\Builder<static> withTrashed(bool $withTrashed = true)
 */
class Contact extends Model
{
    /** @use HasFactory<\Database\Factories\ContactFactory> */
    use Auditable, HasFactory, HasTenant, HasUuid7, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_COMPLAINED = 'complained';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_UNSUBSCRIBED,
        self::STATUS_BOUNCED,
        self::STATUS_COMPLAINED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'email',
        'first_name',
        'last_name',
        'status',
        'custom_fields',
    ];

    /** @return BelongsToMany<ContactList, $this> */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Contacts that are legally and technically safe to mail.
     *
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeMailable(Builder $query): Builder
    {
        $query->where('status', self::STATUS_ACTIVE);

        return $query;
    }

    public function fullName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
        ];
    }
}
