<?php

namespace App\Models;

use App\Tenancy\Auditable;
use App\Tenancy\HasUuid7;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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
 * @property array<string, bool>|null $notification_preferences
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 */
#[Fillable(['tenant_id', 'name', 'email', 'password', 'role', 'notification_preferences'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
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

    /**
     * Categories of mail a user may switch off.
     *
     * Billing and security notices are deliberately absent. They are service
     * messages about an existing contract — the kind a customer would be
     * furious to have missed — and making them optional is how somebody ends
     * up suspended having opted out of the warning.
     *
     * @var array<string, array{label: string, help: string}>
     */
    public const OPTIONAL_NOTIFICATIONS = [
        'product' => [
            'label' => 'Setup tips and occasional check-ins',
            'help' => 'A nudge if your workspace stalls before its first send, and one message if you ever move back to the free plan.',
        ],
        'reports' => [
            'label' => 'Campaign reports',
            'help' => 'A summary of how each campaign performed, sent once it has finished delivering.',
        ],
    ];

    /**
     * Should this user receive mail in the given category?
     *
     * Anything not in OPTIONAL_NOTIFICATIONS is mandatory, so an unknown
     * category fails *open* — a coding mistake must not silently suppress a
     * suspension warning.
     */
    public function wantsNotification(string $category): bool
    {
        if (! array_key_exists($category, self::OPTIONAL_NOTIFICATIONS)) {
            return true;
        }

        // Fails open on a partially-hydrated model too: a `select()` that
        // omitted the column must never be able to silence a warning.
        if (! array_key_exists('notification_preferences', $this->getAttributes())) {
            return true;
        }

        $preferences = $this->notification_preferences;

        return ! is_array($preferences) || ($preferences[$category] ?? true) !== false;
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
            'notification_preferences' => 'array',
        ];
    }
}
