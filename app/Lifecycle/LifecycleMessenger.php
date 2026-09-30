<?php

namespace App\Lifecycle;

use App\Models\LifecycleMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends a lifecycle message to a workspace, at most once, ever.
 *
 * Three rules, each of them learned the expensive way by somebody:
 *
 *  1. **Exactly once.** The claim is a unique insert into `lifecycle_messages`
 *     *before* anything is sent, so two schedulers racing on the same minute
 *     produce one email, not two. A duplicate "your card was declined" reads
 *     as a second decline.
 *
 *  2. **Never fatal.** A lifecycle email is a courtesy wrapped around a
 *     financial operation. If the mailer is down, the payment still has to
 *     settle and the suspension still has to apply — so every failure here is
 *     reported and swallowed.
 *
 *  3. **The right humans.** Billing mail goes to the people who can act on it
 *     (owners and admins), not to every viewer in the workspace, and never to
 *     nobody: an orphaned workspace falls back to its oldest member.
 */
final class LifecycleMessenger
{
    /**
     * Send a notification to a workspace unless this exact message has
     * already gone out.
     *
     * @param  callable(): Notification  $factory  built lazily, so nothing is
     *                                             constructed for a message
     *                                             that will not be sent
     * @return bool whether this call is the one that sent it
     */
    public function sendOnce(Tenant $tenant, string $key, callable $factory): bool
    {
        if (! config('platform.lifecycle.enabled', true)) {
            return false;
        }

        if (! $this->claim($tenant, $key)) {
            return false;
        }

        $recipients = $this->recipientsFor($tenant);

        if ($recipients === []) {
            Log::warning('Lifecycle message skipped: workspace has no reachable recipient', [
                'tenant_id' => $tenant->getKey(),
                'key' => $key,
            ]);

            // The claim stands. Re-running tomorrow against the same empty
            // workspace would only log the same line again.
            return false;
        }

        try {
            Notifier::send($recipients, $factory());
        } catch (Throwable $e) {
            // Release the claim so the next scheduler pass can try again —
            // a transient SMTP failure must not silently consume the only
            // warning a customer was going to get before suspension.
            $this->release($tenant, $key);

            report($e);

            return false;
        }

        LifecycleMessage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('key', $key)
            ->update(['recipients' => count($recipients)]);

        return true;
    }

    /** Has this message already been sent to this workspace? */
    public function alreadySent(Tenant $tenant, string $key): bool
    {
        return LifecycleMessage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('key', $key)
            ->exists();
    }

    /**
     * People who can actually do something about a billing message.
     *
     * @return list<User>
     */
    public function recipientsFor(Tenant $tenant): array
    {
        $decision = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('role', [\App\Models\Role::OWNER, \App\Models\Role::ADMIN])
            ->orderByRaw("case when role = 'owner' then 0 else 1 end")
            ->get();

        if ($decision->isEmpty()) {
            // Better a viewer than nobody: somebody has to be told the
            // workspace is about to stop sending.
            $decision = User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->oldest()
                ->limit(1)
                ->get();
        }

        return $decision->all();
    }

    /**
     * Atomically reserve this message. Returns false when another process
     * (or an earlier run) already holds it.
     */
    private function claim(Tenant $tenant, string $key): bool
    {
        try {
            // No explicit id: HasUuid7 stamps one on `creating`, and passing
            // it here trips Model::preventSilentlyDiscardingAttributes().
            LifecycleMessage::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->getKey(),
                'key' => $key,
                'type' => Str::before($key, ':'),
                'recipients' => 0,
                'sent_at' => now(),
            ]);

            return true;
        } catch (QueryException) {
            // Unique violation: somebody else got here first. That is the
            // whole point of the constraint.
            return false;
        }
    }

    private function release(Tenant $tenant, string $key): void
    {
        LifecycleMessage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('key', $key)
            ->delete();
    }
}
