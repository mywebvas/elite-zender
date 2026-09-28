<?php

namespace App\Models;

use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tenant-scoped contact label.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 */
class Tag extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory, HasTenant, HasUuid7;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'name',
    ];

    /** @return BelongsToMany<Contact, $this> */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class);
    }

    /**
     * Resolve a comma-separated tag string into tag ids, creating the missing
     * ones. Tenant assignment is handled by the HasTenant trait.
     *
     * @return list<string>
     */
    public static function idsForNames(string $csv): array
    {
        $names = collect(explode(',', $csv))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->take(50);

        return $names
            ->map(fn (string $name) => static::firstOrCreate(['name' => $name])->getKey())
            ->values()
            ->all();
    }
}
