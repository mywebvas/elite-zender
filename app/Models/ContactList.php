<?php

namespace App\Models;

use App\Tenancy\Auditable;
use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A named audience segment inside a workspace.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ContactList extends Model
{
    /** @use HasFactory<\Database\Factories\ContactListFactory> */
    use Auditable, HasFactory, HasTenant, HasUuid7, SoftDeletes;

    /**
     * `tenant_id` is fillable so seeders, importers and tests can create a
     * list for an explicit workspace. Requests never reach this array
     * unfiltered — controllers pass validated payloads only, and HasTenant
     * stamps the current tenant when the attribute is absent.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
    ];

    /** @return BelongsToMany<Contact, $this> */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
