<?php

namespace App\Policies;

use App\Models\Role;

/**
 * SMTP credentials are the most sensitive tenant asset (they can send mail as
 * the customer's domain), so they are admin-only for writes.
 */
class SmtpAccountPolicy extends TenantResourcePolicy
{
    protected string $writeRole = Role::ADMIN;

    protected string $deleteRole = Role::ADMIN;
}
