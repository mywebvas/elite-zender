<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single platform configuration override.
 *
 * @property string $key
 * @property string|null $value
 * @property string $type
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['key', 'value', 'type', 'group', 'is_secret'];

    /** Secrets are decrypted on read and encrypted on write by the cast. */
    protected $hidden = ['value'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }
}
