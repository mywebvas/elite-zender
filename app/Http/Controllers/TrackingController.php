<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Tenant;
use App\Support\SafeRedirect;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrackingController extends Controller
{
    /** Transparent 1×1 GIF served for open tracking. Base64 kept inline to avoid a disk read per pixel. */
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /** 1×1 transparent GIF — no auth required, rate-limited at route level. */
    public function open(Request $request, string $campaign_id, string $contact_id): Response
    {
        $this->recordEvent($campaign_id, function (Campaign $campaign) use ($request, $campaign_id, $contact_id): void {
            CampaignEvent::firstOrCreate(
                ['campaign_id' => $campaign_id, 'contact_id' => $contact_id, 'type' => CampaignEvent::TYPE_OPEN],
                [
                    'tenant_id' => $campaign->tenant_id,
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr($request->userAgent() ?? '', 0, 500),
                ],
            );

            $this->fireEngagementTrigger(
                \App\Models\Automation::TRIGGER_CAMPAIGN_OPENED,
                $campaign_id,
                $contact_id,
            );
        });

        return response((string) base64_decode(self::PIXEL, true), 200)
            ->header('Content-Type', 'image/gif')
            ->header('Content-Disposition', 'inline')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /** Click relay — validates destination URL before redirecting. */
    public function click(Request $request, string $campaign_id, string $contact_id): RedirectResponse
    {
        $rawUrl = (string) $request->query('url', '');

        $decodedUrl = $rawUrl === '' ? false : base64_decode($rawUrl, true);

        if ($decodedUrl === false || ! SafeRedirect::isAllowed($decodedUrl)) {
            Log::warning('TrackingController: blocked unsafe redirect', [
                'raw_url' => mb_substr($rawUrl, 0, 200),
                'ip' => $request->ip(),
                'campaign' => $campaign_id,
            ]);

            abort(422, 'Invalid redirect URL.');
        }

        $this->recordEvent($campaign_id, function (Campaign $campaign) use ($request, $campaign_id, $contact_id, $decodedUrl): void {
            CampaignEvent::create([
                'tenant_id' => $campaign->tenant_id,
                'campaign_id' => $campaign_id,
                'contact_id' => $contact_id,
                'type' => CampaignEvent::TYPE_CLICK,
                'url' => mb_substr($decodedUrl, 0, 1000),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr($request->userAgent() ?? '', 0, 500),
            ]);

            $this->fireEngagementTrigger(
                \App\Models\Automation::TRIGGER_CAMPAIGN_CLICKED,
                $campaign_id,
                $contact_id,
            );
        });

        return redirect()->away($decodedUrl);
    }

    /**
     * Enrol the contact into any automation watching this engagement.
     *
     * Wrapped so a misbehaving automation can never stop a tracking pixel from
     * returning its GIF — the recipient's mail client is waiting on it.
     */
    private function fireEngagementTrigger(string $trigger, string $campaignId, string $contactId): void
    {
        try {
            $contact = \App\Models\Contact::withoutGlobalScopes()->find($contactId);

            if ($contact !== null) {
                app(\App\Automations\AutomationEngine::class)
                    ->trigger($trigger, $contact, ['campaign_id' => $campaignId]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Resolve the campaign's tenant, run the writer inside that context and
     * always restore the previous context.
     *
     * The old code called `TenantContext::set(null)` on the happy path only —
     * any exception (or an early return) left the tenant bound, which under
     * Octane means the *next* request on that worker inherits it.
     *
     * @param  callable(Campaign): void  $writer
     */
    private function recordEvent(string $campaignId, callable $writer): void
    {
        $campaign = Campaign::withoutGlobalScopes()->find($campaignId);

        if ($campaign === null) {
            // Unknown campaign: still return a valid pixel/redirect so we never
            // confirm or deny the existence of an id to a scanner.
            return;
        }

        $tenant = Tenant::find($campaign->tenant_id);

        TenantContext::run($tenant, fn () => $writer($campaign));
    }
}
