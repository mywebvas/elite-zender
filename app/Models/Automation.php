<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Tenancy\HasTenant;

class Automation extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'trigger_type',
        'trigger_config',
        'is_active',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function steps()
    {
        return $this->hasMany(AutomationStep::class)->orderBy('order_index');
    }
}
