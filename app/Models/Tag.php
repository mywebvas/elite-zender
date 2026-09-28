<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Tenancy\HasTenant;

class Tag extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'tenant_id',
        'name',
    ];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class);
    }
}
