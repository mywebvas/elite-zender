<?php

namespace App\Jobs;

use App\Mail\CampaignEmail;
use App\Models\Campaign;
use App\Models\Contact;
use App\Services\SpinSyntaxService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCampaignChunkJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /** Retry 3 times on transient failures. */
    public int $tries = 3;

    /** Exponential backoff: wait 30s, then 2min, then 5min before retrying. */
    public array $backoff = [30, 120, 300];

    public Campaign $campaign;
    public array $contactIds;

    public function __construct(Campaign $campaign, array $contactIds)
    {
        $this->campaign   = $campaign;
        $this->contactIds = $contactIds;
    }

    public function handle(SpinSyntaxService $spintax): void
    {
        // Bind tenant for this job context
        $tenant = $this->campaign->tenant;
        TenantContext::set($tenant);

        // Fetch contacts
        $contacts = Contact::whereIn('id', $this->contactIds)->get();
        if ($contacts->isEmpty()) {
            return;
        }

        // Get assigned SMTPs (fallback to all active tenant SMTPs)
        $smtps = $this->campaign->smtpAccounts()->get();
        if ($smtps->isEmpty()) {
            $smtps = $tenant->smtpAccounts()->where('status', 'active')->get();
        }

        if ($smtps->isEmpty()) {
            Log::error('SendCampaignChunkJob: no SMTP accounts', ['campaign' => $this->campaign->id]);
            $this->campaign->update(['status' => 'paused']);
            return;
        }

        // ─── Build per-SMTP config ONCE — not inside the contact loop ─────────
        $smtpConfigs = [];
        foreach ($smtps as $smtp) {
            $mailerKey = "smtp_{$smtp->id}";

            config(["mail.mailers.{$mailerKey}" => [
                'transport'  => 'smtp',
                'host'       => $smtp->host,
                'port'       => $smtp->port,
                'encryption' => $smtp->encryption ?? 'tls',
                'username'   => $smtp->username,
                'password'   => $smtp->password,
                'timeout'    => 15,
            ]]);

            $smtpConfigs[$smtp->id] = [
                'mailer_key' => $mailerKey,
                'from_email' => $smtp->from_email,
                'from_name'  => $smtp->from_name,
                'smtp'       => $smtp,
            ];
        }

        $smtpList  = array_values($smtpConfigs);
        $smtpCount = count($smtpList);
        $index     = 0;

        foreach ($contacts as $contact) {
            // Round-robin SMTP selection
            $config = $smtpList[$index % $smtpCount];
            $index++;

            // Compile spin syntax per-contact
            $data          = $contact->toArray();
            $data['Name']  = $contact->first_name ?? '';

            $subject  = $spintax->compile($this->campaign->subject ?? '', $data);
            $htmlBody = $spintax->compile($this->campaign->body_html ?? '', $data);
            $textBody = $spintax->compile($this->campaign->body_text ?? '', $data);

            // Inject tracking pixel + rewrite links
            $htmlBody = $this->injectTracking($htmlBody, $this->campaign->id, $contact->id);

            // Append Unsubscribe Link explicitly to HTML and Text bodies
            $unsubUrl = route('unsubscribe', ['campaign' => $this->campaign->id, 'contact' => $contact->id]);
            $unsubHtml = "<div style=\"margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: center;\"><p>You are receiving this because you opted in. <a href=\"{$unsubUrl}\" style=\"color: #64748b; text-decoration: underline;\">Unsubscribe here</a>.</p></div>";

            if (stripos($htmlBody, '</body>') !== false) {
                $htmlBody = str_ireplace('</body>', $unsubHtml . "\n</body>", $htmlBody);
            } else {
                $htmlBody .= "\n" . $unsubHtml;
            }

            if ($textBody) {
                $textBody .= "\n\n---\nTo unsubscribe, visit: {$unsubUrl}";
            }

            // Send — isolate each email so one failure doesn't abort the whole chunk
            try {
                $mailable = (new CampaignEmail($subject, $htmlBody, $textBody, $unsubUrl))
                    ->from($config['from_email'], $config['from_name'])
                    ->to($contact->email);

                Mail::mailer($config['mailer_key'])->send($mailable);
            } catch (\Exception $e) {
                Log::error('SendCampaignChunkJob: email send failed', [
                    'campaign'   => $this->campaign->id,
                    'contact'    => $contact->email,
                    'smtp_host'  => $config['smtp']->host,
                    'error'      => $e->getMessage(),
                ]);
                // Continue to next contact — don't abort the whole chunk
            }
        }

        TenantContext::set(null);
    }

    /**
     * Handle job failure after all retries exhausted.
     * Pause the campaign and log for operator review.
     */
    public function failed(\Throwable $e): void
    {
        Log::critical('SendCampaignChunkJob: permanently failed after retries', [
            'campaign'    => $this->campaign->id,
            'contact_ids' => $this->contactIds,
            'error'       => $e->getMessage(),
        ]);

        // Pause campaign to prevent partial sends going unnoticed
        $this->campaign->update(['status' => 'paused']);
    }

    /** Injects tracking pixel and rewrites links in campaign HTML. */
    protected function injectTracking(string $html, string $campaignId, string $contactId): string
    {
        if (empty(trim($html))) {
            return $html;
        }

        // Rewrite HTTP/HTTPS links to tracking relay
        $html = preg_replace_callback('/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/i', function ($matches) use ($campaignId, $contactId) {
            $quote       = $matches[1];
            $originalUrl = $matches[2];

            // Preserve mailto:, tel:, and anchor links untouched
            if (str_starts_with($originalUrl, 'mailto:') ||
                str_starts_with($originalUrl, 'tel:') ||
                str_starts_with($originalUrl, '#')) {
                return $matches[0];
            }

            $encodedUrl  = base64_encode($originalUrl);
            $trackingUrl = route('tracking.click', [
                'campaign' => $campaignId,
                'contact'  => $contactId,
                'url'      => $encodedUrl,
            ]);

            return str_replace(
                "href={$quote}{$originalUrl}{$quote}",
                "href={$quote}{$trackingUrl}{$quote}",
                $matches[0]
            );
        }, $html);

        // Append 1×1 tracking pixel
        $pixelUrl = route('tracking.open', ['campaign' => $campaignId, 'contact' => $contactId]);
        $pixel    = '<img src="' . $pixelUrl . '" width="1" height="1" alt="" style="display:none;" />';

        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $pixel . "\n</body>", $html);
        } else {
            $html .= "\n" . $pixel;
        }

        return $html;
    }
}



