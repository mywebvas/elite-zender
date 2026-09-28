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
    ) {}

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

        if ($contacts->isEmpty()) {
            return;
        }

        $pool = SmtpPool::forCampaign($this->campaign);

        if ($pool->isEmpty()) {
            Log::error('SendCampaignChunkJob: no SMTP capacity available', [
                'campaign' => $this->campaign->getKey(),
                'tenant' => TenantContext::id(),
            ]);

            $this->campaign->update(['status' => Campaign::STATUS_PAUSED]);

            return;
        }

        $registered = [];
        $sent = 0;
        $failed = 0;

        try {
            foreach ($contacts as $contact) {
                /** @var Contact $contact */
                // Hard bounces and complaints are suppressed globally, beyond
                // the contact's own status, so a re-import cannot resurrect a
                // burnt address.
                if (SuppressionEntry::suppresses($contact->email, (string) $this->campaign->tenant_id)) {
                    continue;
                }

                $smtp = $pool->next();

                if ($smtp === null) {
                    // Daily caps reached mid-chunk — requeue the remainder for
                    // later rather than silently dropping recipients.
                    $this->release(3600);

                    return;
                }

                $mailerKey = $this->registerMailer($smtp, $registered);

                $this->deliver($spintax, $contact, $smtp, $mailerKey) ? $sent++ : $failed++;
            }
        } finally {
            $pool->commitUsage();
            $this->forgetMailers($registered);
            $this->recordProgress($sent, $failed);
        }
    }

    /** Atomic counter bump so concurrent chunk workers cannot lose updates. */
    private function recordProgress(int $sent, int $failed): void
    {
        if ($sent === 0 && $failed === 0) {
            return;
        }

        Campaign::withoutGlobalScopes()
            ->whereKey($this->campaign->getKey())
            ->incrementEach(['sent_count' => $sent, 'failed_count' => $failed]);
    }

    private function deliver(
        SpinSyntaxService $spintax,
        Contact $contact,
        SmtpAccount $smtp,
        string $mailerKey,
    ): bool {
        $data = $contact->only(['email', 'first_name', 'last_name']) + ($contact->custom_fields ?? []);
        $data['Name'] = $contact->first_name ?? '';
        $data['Email'] = $contact->email;

        // Seed per recipient so the same contact always receives the same
        // variant (docs/08) — re-sends and previews stay consistent, and A/B
        // analysis is not polluted by random re-rolls.
        $seed = crc32($this->campaign->getKey().'|'.$contact->getKey());

        $subject = $spintax->compile($this->campaign->subject ?? '', $data, $seed);
        $htmlBody = $spintax->compile($this->campaign->body_html ?? '', $data, $seed);
        $textBody = $spintax->compile($this->campaign->body_text ?? '', $data, $seed);

        $htmlBody = $this->injectTracking($htmlBody, (string) $this->campaign->getKey(), (string) $contact->getKey());

        $unsubUrl = UnsubscribeLink::for($this->campaign, $contact);

        $htmlBody = $this->appendHtml($htmlBody, $this->unsubscribeFooter($unsubUrl));

        if ($textBody !== '') {
            $textBody .= "\n\n---\nTo unsubscribe, visit: {$unsubUrl}";
        }

        try {
            $mailable = (new CampaignEmail($subject, $htmlBody, $textBody, $unsubUrl))
                ->from($smtp->from_email, $smtp->from_name)
                ->to($contact->email);

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
        $mailers = config('mail.mailers', []);

        foreach (array_keys($registered) as $key) {
            unset($mailers[$key]);
            Mail::forgetMailers();
        }

        config(['mail.mailers' => $mailers]);
    }

    private function unsubscribeFooter(string $unsubUrl): string
    {
        return '<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: center;">'
            .'<p>You are receiving this because you opted in. '
            .'<a href="'.e($unsubUrl).'" style="color: #64748b; text-decoration: underline;">Unsubscribe here</a>.</p></div>';
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

    /** Injects tracking pixel and rewrites links in campaign HTML. */
    protected function injectTracking(string $html, string $campaignId, string $contactId): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $html = (string) preg_replace_callback(
            '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/i',
            function (array $matches) use ($campaignId, $contactId): string {
                $quote = $matches[1];
                $originalUrl = $matches[2];

                // Preserve mailto:, tel:, anchors and merge tags untouched.
                if (str_starts_with($originalUrl, 'mailto:')
                    || str_starts_with($originalUrl, 'tel:')
                    || str_starts_with($originalUrl, '#')) {
                    return $matches[0];
                }

                $trackingUrl = route('tracking.click', [
                    'campaign' => $campaignId,
                    'contact' => $contactId,
                    'url' => base64_encode($originalUrl),
                ]);

                return str_replace(
                    "href={$quote}{$originalUrl}{$quote}",
                    "href={$quote}{$trackingUrl}{$quote}",
                    $matches[0],
                );
            },
            $html,
        );

        $pixelUrl = route('tracking.open', ['campaign' => $campaignId, 'contact' => $contactId]);

        return $this->appendHtml($html, '<img src="'.$pixelUrl.'" width="1" height="1" alt="" style="display:none;" />');
    }

    /** Insert a fragment just before </body>, or append when there is no body. */
    private function appendHtml(string $html, string $fragment): string
    {
        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $fragment."\n</body>", $html);
        }

        return $html."\n".$fragment;
    }
}
