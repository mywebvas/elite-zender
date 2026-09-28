<?php

namespace App\Models;

use App\Tenancy\Auditable;
use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A publicly embeddable signup form.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $list_id
 * @property string $name
 * @property string $public_key
 * @property list<string>|null $allowed_origins
 * @property bool $is_active
 */
class LeadCaptureForm extends Model
{
    /** @use HasFactory<\Database\Factories\LeadCaptureFormFactory> */
    use Auditable, HasFactory, HasTenant, HasUuid7, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'list_id',
        'name',
        'public_key',
        'allowed_origins',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $form): void {
            $form->public_key ??= 'pk_'.Str::random(40);
        });
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

    /** Is a browser origin permitted to post to this form? */
    public function allowsOrigin(?string $origin): bool
    {
        $allowed = $this->allowed_origins ?? [];

        // No allow-list configured means "any origin" — the same posture as a
        // classic HTML form action.
        return $allowed === [] || ($origin !== null && in_array($origin, $allowed, true));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'allowed_origins' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
