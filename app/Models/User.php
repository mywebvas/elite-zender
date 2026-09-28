<?php

namespace App\Models;

use App\Tenancy\Auditable;
use App\Tenancy\HasUuid7;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A workspace member.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 */
#[Fillable(['tenant_id', 'name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasUuid7, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Roles in ascending order of privilege.
     *
     * @var list<string>
     */
    public const ROLES = [
        Role::VIEWER,
        Role::MEMBER,
        Role::ADMIN,
        Role::OWNER,
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isOwner(): bool
    {
        return $this->role === Role::OWNER;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [Role::OWNER, Role::ADMIN], true);
    }

    /** Can this user mutate tenant data (as opposed to read-only)? */
    public function canWrite(): bool
    {
        return in_array($this->role, [Role::OWNER, Role::ADMIN, Role::MEMBER], true);
    }

    /** Does this user hold at least the given role? */
    public function hasRoleAtLeast(string $role): bool
    {
        $mine = array_search($this->role, self::ROLES, true);
        $needed = array_search($role, self::ROLES, true);

        return $mine !== false && $needed !== false && $mine >= $needed;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
