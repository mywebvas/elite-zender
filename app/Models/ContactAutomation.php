<?php

namespace App\Models;

use App\Tenancy\HasTenant;
use App\Tenancy\HasUuid7;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One contact's journey through one automation.
 *
 * `execute_next_at` is the scheduler's claim column: the runner picks up rows
 * whose time has come, so a `wait` step costs nothing until it is due.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $contact_id
 * @property string $automation_id
 * @property string|null $current_step_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $execute_next_at
 *
 * @method static Builder<static> due()
 */
class ContactAutomation extends Model
{
    use HasTenant, HasUuid7;

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'contact_id', 'automation_id', 'current_step_id',
        'status', 'execute_next_at',
    ];

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Automation, $this> */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /** @return BelongsTo<AutomationStep, $this> */
    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(AutomationStep::class, 'current_step_id');
    }

    /**
     * Enrolments the runner should pick up now.
     *
     * @param  Builder<ContactAutomation>  $query
     * @return Builder<ContactAutomation>
     */
    public function scopeDue(Builder $query): Builder
    {
        $query->where('status', self::STATUS_RUNNING)
            ->whereNotNull('execute_next_at')
            ->where('execute_next_at', '<=', now());

        return $query;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['execute_next_at' => 'datetime'];
    }
}
