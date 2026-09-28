<?php

namespace App\Models;

use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant root entity. Row-scoped models belong to exactly one tenant.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property array<string, mixed>|null $settings
 * @property string $status
 */
class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory, HasUuid7, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'settings', 'status'];

    /** @return HasMany<SmtpAccount, $this> */
    public function smtpAccounts(): HasMany
    {
        return $this->hasMany(SmtpAccount::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Campaign, $this> */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Short, stable cache/Redis namespace for this tenant.
     * See docs/README.md decision #6 (per-tenant key prefix).
     */
    public function keyPrefix(): string
    {
        return 't:'.substr(str_replace('-', '', (string) $this->getKey()), 0, 12).':';
    }

    /** Read a namespaced setting with a default. */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    /** The workspace's reporting timezone (used for daily quota resets). */
    public function timezone(): string
    {
        $tz = $this->setting('timezone');

        return is_string($tz) && $tz !== '' ? $tz : (string) config('app.timezone', 'UTC');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
