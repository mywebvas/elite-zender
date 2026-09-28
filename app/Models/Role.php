<?php

namespace App\Models;

/**
 * Workspace roles (docs/06-SECURITY-COMPLIANCE.md — RBAC).
 *
 * Kept as plain constants rather than an enum so the value can be stored in a
 * `string` column and compared without casting ceremony across Blade, policies
 * and validation rules.
 */
final class Role
{
    public const OWNER = 'owner';

    public const ADMIN = 'admin';

    public const MEMBER = 'member';

    public const VIEWER = 'viewer';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::OWNER, self::ADMIN, self::MEMBER, self::VIEWER];
    }
}
