<?php

namespace App\Jobs;

use App\Mail\CampaignEmail;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\SmtpAccount;
use App\Models\SuppressionEntry;
use App\Services\SmtpPool;
use App\Services\SpinSyntaxService;
use App\Support\UnsubscribeLink;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one chunk of a campaign through the tenant's SMTP pool.
 */
class SendCampaignChunkJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /** Retry 3 times on transient failures. */
    public int $tries = 3;

    /**
     * Exponential backoff: wait 30s, then 2min, then 5min before retrying.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * @param  list<string>  $contactIds
     */
    public function __construct(
        public Campaign $campaign,
        public array $contactIds,
    ) {
        // Sending is the product. It runs ahead of imports and housekeeping.
        $this->onQueue('high');
    }

    public function handle(SpinSyntaxService $spintax): void
    {
        TenantContext::run($this->campaign->tenant, function () use ($spintax): void {
            $this->send($spintax);
        });
    }

    private function send(SpinSyntaxService $spintax): void
    {
        // Suppression is enforced at query level: unsubscribed, bounced and
        // complained contacts never enter the loop, even if they were mailable
        // when the chunk was queued.
        $contacts = Contact::mailable()
            ->whereIn('id', $this->contactIds)
            ->get();

        // Anyone who dropped out of `mailable()` between fan-out and now is
        // still part of `recipients_count`. Recording them as skipped is what
        // lets the campaign ever reach `completed`.
        $vanished = count($this->contactIds) - $contacts->count();

        if ($contacts->isEmpty()) {
            $this->recordProgress(0, 0, $vanished);

            return;
        }

        $allowance = $this->monthlyAllowance();

        if ($allowance !== null && $allowance <= 0) {
            // The plan's monthly allowance is spent. Stop the campaign rather
            // than quietly delivering (and billing) past what was sold.
            $this->pause('monthly sending allowance exhausted');
            $this->recordProgress(0, 0, $vanished);

            return;
        }

        $pool = SmtpPool::forCampaign($this->campaign);

        if ($pool->isEmpty()) {
            Log::error('SendCampaignChunkJob: no SMTP capacity available', [
                'campaign' => $this->campaign->getKey(),
                'tenant' => TenantContext::id(),
            ]);

            $this->pause('no SMTP relay with remaining quota');
            $this->recordProgress(0, 0, $vanished);

            return;
        }

        $registered = [];
        $sent = 0;
        $failed = 0;
        $skipped = $vanished;

        /** @var list<string> $deferred contacts this chunk could not attempt */
        $deferred = [];

        try {
            foreach ($contacts as $contact) {
                /** @var Contact $contact */
                // Hard bounces and complaints are suppressed globally, beyond
                // the contact's own status, so a re-import cannot resurrect a
                // burnt address.
                if (SuppressionEntry::suppresses($contact->email, (string) $this->campaign->tenant_id)) {
                    $skipped++;

                    continue;
                }

                if ($allowance !== null && $sent >= $allowance) {
                    // Everything past the allowance stays unsent and unbilled.
                    $this->pause('monthly sending allowance exhausted');

                    break;
                }

                $smtp = $pool->next();

                if ($smtp === null) {
                    // Daily relay caps reached mid-chunk. Hand the *remainder*
                    // to a fresh job rather than releasing this one: releasing
                    // replays the whole chunk, and everyone already delivered
                    // receives the campaign a second time.
                    $deferred[] = (string) $contact->getKey();

                    continue;
                }

                $mailerKey = $this->registerMailer($smtp, $registered);

                $this->deliver($spintax, $contact, $smtp, $mailerKey) ? $sent++ : $failed++;
            }
        } finally {
            $pool->commitUsage();
            $this->forgetMailers($registered);
            $this->recordProgress($sent, $failed, $skipped);
        }

        // Deliberately outside the `finally`: if the chunk threw, the queue
        // will retry this job, and queueing the leftovers as well would fan a
        // failure out into two overlapping jobs.
        $this->deferRemainder($deferred);
    }

    /**
     * Messages the workspace may still send this month, or null when the plan
     * is unlimited.
     */
    private function monthlyAllowance(): ?int
    {
        $tenant = $this->campaign->tenant;

        if ($tenant === null) {
            return null;
        }

        return app(\App\Billing\PlanGate::class)->remaining($tenant, 'emails_per_month');
    }

    /**
     * Re-queue the recipients this chunk could not attempt.
     *
     * A brand-new job carrying only the leftovers: no already-delivered
     * contact is ever in it, so a relay running out of quota mid-chunk costs
     * a delay, not a duplicate send.
     *
     * @param  list<string>  $contactIds
     */
    private function deferRemainder(array $contactIds): void
    {
        if ($contactIds === []) {
            return;
        }

        Log::info('SendCampaignChunkJob: deferring recipients until relay quota resets', [
            'campaign' => $this->campaign->getKey(),
            'deferred' => count($contactIds),
        ]);

        self::dispatch($this->campaign, $contactIds)->delay(now()->addHour());
    }

    private function pause(string $reason): void
    {
        Log::warning('SendCampaignChunkJob: campaign paused', [
            'campaign' => $this->campaign->getKey(),
            'tenant' => (string) $this->campaign->tenant_id,
            'reason' => $reason,
        ]);

        Campaign::withoutGlobalScopes()
            ->whereKey($this->campaign->getKey())
            ->where('status', Campaign::STATUS_SENDING)
            ->update(['status' => Campaign::STATUS_PAUSED]);
    }

    /** Atomic counter bump so concurrent chunk workers cannot lose updates. */
    private function recordProgress(int $sent, int $failed, int $skipped = 0): void
    {
        if ($sent === 0 && $failed === 0 && $skipped === 0) {
            return;
        }

        Campaign::withoutGlobalScopes()
            ->whereKey($this->campaign->getKey())
            ->incrementEach([
                'sent_count' => $sent,
                'failed_count' => $failed,
                'skipped_count' => $skipped,
            ]);

        // Billable usage counts messages actually handed to a relay, never the
        // size of the list.
        app(\App\Billing\PlanGate::class)->recordEmailsSent((string) $this->campaign->tenant_id, $sent);
    }

    private function deliver(
        SpinSyntaxService $spintax,
        Contact $contact,
        SmtpAccount $smtp,
        string $mailerKey,
    ): bool {
        $data = \App\Support\MergeTags::forContact($contact);

        // Seed per recipient so the same contact always receives the same
        // variant (docs/08) — re-sends and previews stay consistent, and A/B
        // analysis is not polluted by random re-rolls.
        $seed = crc32($this->campaign->getKey().'|'.$contact->getKey());

        $subject = $spintax->compile($this->campaign->subject ?? '', $data, $seed);
        $htmlBody = $spintax->compile($this->campaign->body_html ?? '', $data, $seed);
        $textBody = $spintax->compile($this->campaign->body_text ?? '', $data, $seed);

        $htmlBody = app(\App\Services\CampaignTracking::class)
            ->inject($htmlBody, (string) $this->campaign->getKey(), (string) $contact->getKey());

        $unsubUrl = UnsubscribeLink::for($this->campaign, $contact);

        $htmlBody = UnsubscribeLink::appendFooter($htmlBody, $unsubUrl);
        $textBody = UnsubscribeLink::appendTextFooter($textBody, $unsubUrl);

        try {
            $mailable = (new CampaignEmail($subject, $htmlBody, $textBody, $unsubUrl))
                ->from($smtp->from_email, $smtp->from_name)
                ->to($contact->email);

            if (filled($this->campaign->reply_to)) {
                $mailable->replyTo($this->campaign->reply_to);
            }

            Mail::mailer($mailerKey)->send($mailable);

            return true;
        } catch (Throwable $e) {
            // One bad recipient must never abort the chunk.
            Log::error('SendCampaignChunkJob: email send failed', [
                'campaign' => $this->campaign->getKey(),
                'contact' => $contact->getKey(),
                'smtp_account' => $smtp->getKey(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Register a throwaway mailer for this relay.
     *
     * The config entry is removed again in `forgetMailers()`; under Octane the
     * config repository is shared between requests/jobs, so leaving per-tenant
     * SMTP credentials behind would leak them into the next job on the worker.
     *
     * @param  array<string, string>  $registered
     */
    private function registerMailer(SmtpAccount $smtp, array &$registered): string
    {
        $key = 'smtp_'.$smtp->getKey();

        if (isset($registered[$key])) {
            return $key;
        }

        config([
            "mail.mailers.{$key}" => [
                'transport' => 'smtp',
                'host' => $smtp->host,
                'port' => $smtp->port,
                'encryption' => $smtp->encryption === 'none' ? null : $smtp->encryption,
                'username' => $smtp->username,
                'password' => $smtp->password,
                'timeout' => 15,
            ],
        ]);

        $registered[$key] = $key;

        return $key;
    }

    /** @param array<string, string> $registered */
    private function forgetMailers(array $registered): void
    {
        if ($registered === []) {
            return;
        }

        $mailers = config('mail.mailers', []);

        foreach (array_keys($registered) as $key) {
            unset($mailers[$key]);
        }

        config(['mail.mailers' => $mailers]);

        // Once, after the config is clean — resolved mailers are cached by key
        // and would otherwise keep the credentials alive in memory.
        Mail::forgetMailers();
    }

    /**
     * Handle job failure after all retries exhausted.
     * Pause the campaign and log for operator review.
     */
    public function failed(Throwable $e): void
    {
        Log::critical('SendCampaignChunkJob: permanently failed after retries', [
            'campaign' => $this->campaign->getKey(),
            'contact_ids' => $this->contactIds,
            'error' => $e->getMessage(),
        ]);

        $this->campaign->update(['status' => Campaign::STATUS_PAUSED]);
    }
}
