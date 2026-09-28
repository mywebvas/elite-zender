<?php

namespace App\Models;

use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A platform operator.
 *
 * Deliberately NOT a `User` with a flag: a flag means one bug in the tenant
 * scope, one leaked customer session or one mistaken role assignment puts the
 * whole platform in reach. Separate table, separate guard, separate cookie.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property bool $is_active
 */
class Admin extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\AdminFactory> */
    use HasFactory, HasUuid7, Notifiable, SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPPORT = 'support';

    /** Ascending privilege. */
    public const ROLES = [self::ROLE_SUPPORT, self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN];

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** Unrestricted: billing changes, plan edits, refunds, other admins. */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** May act on customer data (suspend, change plan, mark paid). */
    public function canManage(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    /** Read-only support staff can still impersonate to reproduce a report. */
    public function canImpersonate(): bool
    {
        return $this->is_active;
    }

    public function hasRoleAtLeast(string $role): bool
    {
        $mine = array_search($this->role, self::ROLES, true);
        $needed = array_search($role, self::ROLES, true);

        return $mine !== false && $needed !== false && $mine >= $needed;
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<AdminImpersonation, $this> */
    public function impersonations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdminImpersonation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
