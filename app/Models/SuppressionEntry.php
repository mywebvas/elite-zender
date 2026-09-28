<?php

namespace App\Models;

use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Model;

/**
 * A hashed "never mail this address again" record.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $email_hash
 * @property string $reason
 */
class SuppressionEntry extends Model
{
    use HasUuid7;

    public const UPDATED_AT = null;

    public const REASON_HARD_BOUNCE = 'bounce_hard';

    public const REASON_COMPLAINT = 'complaint';

    public const REASON_UNSUBSCRIBE = 'unsubscribe';

    public const REASON_MANUAL = 'manual';

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'email_hash', 'reason', 'detail'];

    /**
     * Hash an address for storage/lookup.
     *
     * Keyed with the application key so a stolen table cannot be brute-forced
     * against a dictionary of addresses offline.
     */
    public static function hash(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('app.key'));
    }

    public static function suppress(string $email, string $reason, ?string $tenantId = null, ?string $detail = null): self
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId, 'email_hash' => static::hash($email)],
            ['reason' => $reason, 'detail' => $detail],
        );
    }

    public static function suppresses(string $email, ?string $tenantId = null): bool
    {
        $hash = static::hash($email);

        return static::query()
            ->where('email_hash', $hash)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->exists();
    }
}
