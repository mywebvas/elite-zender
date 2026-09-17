<?php

namespace App\Models;

use App\Tenancy\Auditable;
use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SMTP accounts per tenant. Passwords encrypted (see Security doc).
 * Tenant-scoped via HasTenant global scope.
 */
class SmtpAccount extends Model
{
    use Auditable, HasFactory, HasTenant, HasUuid7, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'host', 'port', 'username', 'password',
        'encryption', 'from_email', 'from_name', 'daily_limit', 'sent_today',
        'health_score', 'status', 'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'daily_limit' => 'integer',
            'sent_today' => 'integer',
            'health_score' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }
}
