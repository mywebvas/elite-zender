<?php

namespace App\Automations;

use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\Contact;
use App\Models\ContactAutomation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Enrolment: deciding *who* enters *which* automation, and when.
 *
 * Kept separate from StepRunner (which decides what happens next) because the
 * two have completely different failure modes. Enrolment is called inline from
 * request handlers and must be cheap and non-throwing — a webhook that fails
 * because an unrelated automation is misconfigured is a worse outcome than the
 * automation simply not running.
 */
final class AutomationEngine
{
    public function __construct(
        private readonly StepRunner $runner,
    ) {}

    /**
     * Fire a trigger for a contact.
     *
     * @param  array<string, mixed>  $context  tag name, list id, campaign id…
     * @return int number of automations the contact was enrolled into
     */
    public function trigger(string $triggerType, Contact $contact, array $context = []): int
    {
        // Never enrol someone we are not allowed to mail. Doing so would build
        // a queue of messages that can only ever be discarded at send time.
        if ($contact->status !== Contact::STATUS_ACTIVE) {
            return 0;
        }

        $automations = Automation::withoutGlobalScopes()
            ->where('tenant_id', $contact->tenant_id)
            ->where('trigger_type', $triggerType)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->with(['steps' => fn ($q) => $q->orderBy('order_index')])
            ->get();

        $enrolled = 0;

        foreach ($automations as $automation) {
            if (! $this->matchesTriggerConfig($automation, $context)) {
                continue;
            }

            try {
                if ($this->enrol($automation, $contact) !== null) {
                    $enrolled++;
                }
            } catch (Throwable $e) {
                // One broken automation must not take down the request that
                // fired the trigger (a signup, an import row, a click).
                report($e);
            }
        }

        return $enrolled;
    }

    /**
     * Put a contact at the start of an automation.
     *
     * Returns null when the contact is already enrolled — re-entry is
     * deliberately not allowed, because the obvious alternative (re-enrol on
     * every trigger) turns a "tag added" automation into a mail loop.
     */
    public function enrol(Automation $automation, Contact $contact): ?ContactAutomation
    {
        $firstStep = $automation->steps->first();

        if ($firstStep === null) {
            return null;
        }

        return DB::transaction(function () use ($automation, $contact, $firstStep): ?ContactAutomation {
            $existing = ContactAutomation::withoutGlobalScopes()
                ->where('contact_id', $contact->getKey())
                ->where('automation_id', $automation->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return null;
            }

            return ContactAutomation::withoutGlobalScopes()->create([
                'tenant_id' => $contact->tenant_id,
                'contact_id' => $contact->getKey(),
                'automation_id' => $automation->getKey(),
                'current_step_id' => $firstStep->getKey(),
                'status' => ContactAutomation::STATUS_RUNNING,
                // Due immediately; the runner applies any wait when it gets there.
                'execute_next_at' => now(),
            ]);
        });
    }

    /**
     * Advance every enrolment whose time has come.
     *
     * Rows are claimed one at a time with a row lock so several workers can run
     * the queue concurrently without executing the same step twice — sending
     * the same person the same email twice is the failure mode that matters.
     *
     * @return array{processed: int, completed: int, failed: int}
     */
    public function processDue(int $limit = 500): array
    {
        $processed = 0;
        $completed = 0;
        $failed = 0;

        $ids = ContactAutomation::withoutGlobalScopes()
            ->due()
            ->orderBy('execute_next_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            $outcome = $this->processOne((string) $id);

            match ($outcome) {
                'skipped' => null,
                'completed' => [$processed++, $completed++],
                'failed' => [$processed++, $failed++],
                default => $processed++,
            };
        }

        return ['processed' => $processed, 'completed' => $completed, 'failed' => $failed];
    }

    /** @return 'advanced'|'completed'|'failed'|'skipped' */
    public function processOne(string $enrolmentId): string
    {
        /** @var ContactAutomation|null $enrolment */
        $enrolment = DB::transaction(function () use ($enrolmentId) {
            $row = ContactAutomation::withoutGlobalScopes()
                ->whereKey($enrolmentId)
                ->lockForUpdate()
                ->first();

            // Re-check under the lock: another worker may have taken it in the
            // gap between the id query and this transaction.
            if ($row === null || $row->status !== ContactAutomation::STATUS_RUNNING
                || $row->execute_next_at === null || $row->execute_next_at->isFuture()) {
                return null;
            }

            // Push the claim into the future immediately so a concurrent worker
            // skips it even before this one finishes.
            $row->forceFill(['execute_next_at' => now()->addMinutes(15)])->save();

            return $row;
        });

        if ($enrolment === null) {
            return 'skipped';
        }

        $step = $enrolment->current_step_id === null
            ? null
            : AutomationStep::find($enrolment->current_step_id);

        if ($step === null) {
            return $this->complete($enrolment);
        }

        try {
            $outcome = $this->runner->run($step, $enrolment);
        } catch (Throwable $e) {
            Log::error('Automation step failed', [
                'enrolment' => $enrolment->getKey(),
                'step' => $step->getKey(),
                'type' => $step->type,
                'error' => $e->getMessage(),
            ]);

            // Pause rather than retry forever: a misconfigured webhook or a
            // deleted campaign will not fix itself, and a hot loop against a
            // customer's endpoint is worse than a stalled automation.
            $enrolment->forceFill([
                'status' => ContactAutomation::STATUS_PAUSED,
                'execute_next_at' => null,
            ])->save();

            return 'failed';
        }

        // A condition that failed, or a contact who unsubscribed mid-journey:
        // end the enrolment rather than marching on to the next step.
        if ($outcome->stop) {
            return $this->complete($enrolment);
        }

        $next = $this->nextStep($step, $outcome->nextStepId);

        if ($next === null) {
            return $this->complete($enrolment);
        }

        // A wait step means "run the *next* step at T", so the cursor advances
        // now and only the clock is deferred. Parking without advancing would
        // re-run the same wait when it came due — an automation that waits for
        // ever and never delivers.
        $enrolment->forceFill([
            'current_step_id' => $next->getKey(),
            'execute_next_at' => $outcome->waitUntil ?? now(),
        ])->save();

        return 'advanced';
    }

    private function nextStep(AutomationStep $current, ?string $explicitNextId): ?AutomationStep
    {
        if ($explicitNextId !== null) {
            return AutomationStep::find($explicitNextId);
        }

        return AutomationStep::where('automation_id', $current->automation_id)
            ->where('order_index', '>', $current->order_index)
            ->orderBy('order_index')
            ->first();
    }

    /** @return 'completed' */
    private function complete(ContactAutomation $enrolment): string
    {
        $enrolment->forceFill([
            'status' => ContactAutomation::STATUS_COMPLETED,
            'current_step_id' => null,
            'execute_next_at' => null,
        ])->save();

        return 'completed';
    }

    /**
     * Does the firing context satisfy the automation's trigger configuration?
     *
     * An unset config means "any" — a `tag_added` automation with no tag named
     * fires for every tag, which is the least surprising reading.
     *
     * @param  array<string, mixed>  $context
     */
    private function matchesTriggerConfig(Automation $automation, array $context): bool
    {
        $config = array_filter($automation->trigger_config ?? [], static fn ($v) => $v !== null && $v !== '');

        foreach ($config as $key => $expected) {
            if (! array_key_exists($key, $context)) {
                continue;
            }

            if ((string) $context[$key] !== (string) $expected) {
                return false;
            }
        }

        return true;
    }
}
