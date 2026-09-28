<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'automation_id',
        'parent_step_id',
        'type',
        'config',
        'order_index',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function automation()
    {
        return $this->belongsTo(Automation::class);
    }
}
