<?php

namespace App\Models;

use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant root entity. Row-scoped models belong to one tenant.
 */
class Tenant extends Model
{
    use HasFactory, HasUuid7, SoftDeletes;

    protected $fillable = ['name', 'slug', 'settings', 'status'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function smtpAccounts()
    {
        return $this->hasMany(SmtpAccount::class);
    }
}
