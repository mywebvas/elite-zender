<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    /** 1×1 transparent GIF — no auth required, rate-limited at route level. */
    public function open(Request $request, string $campaign_id, string $contact_id)
    {
        $campaign = Campaign::withoutGlobalScopes()->find($campaign_id);

        if ($campaign) {
            $tenant = \App\Models\Tenant::find($campaign->tenant_id);
            \App\Tenancy\TenantContext::set($tenant);

            CampaignEvent::firstOrCreate(
                ['campaign_id' => $campaign_id, 'contact_id' => $contact_id, 'type' => 'open'],
                [
                    'tenant_id'  => $campaign->tenant_id,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                ]
            );

            \App\Tenancy\TenantContext::set(null);
        }

        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /** Click relay — validates destination URL before redirecting. */
    public function click(Request $request, string $campaign_id, string $contact_id)
    {
        $rawUrl = $request->query('url');

        if (!$rawUrl) {
            return abort(404);
        }

        // Decode Base64-encoded URL
        $decodedUrl = base64_decode($rawUrl, true);

        // Security: validate it is an absolute HTTP/HTTPS URL
        if (!$decodedUrl || !$this->isSafeUrl($decodedUrl)) {
            Log::warning('TrackingController: blocked unsafe redirect', [
                'raw_url'    => $rawUrl,
                'decoded'    => $decodedUrl,
                'ip'         => $request->ip(),
                'campaign'   => $campaign_id,
            ]);
            return abort(422, 'Invalid redirect URL.');
        }

        $campaign = Campaign::withoutGlobalScopes()->find($campaign_id);

        if ($campaign) {
            $tenant = \App\Models\Tenant::find($campaign->tenant_id);
            \App\Tenancy\TenantContext::set($tenant);

            CampaignEvent::create([
                'tenant_id'  => $campaign->tenant_id,
                'campaign_id' => $campaign_id,
                'contact_id'  => $contact_id,
                'type'        => 'click',
                'url'         => substr($decodedUrl, 0, 1000),
                'ip_address'  => $request->ip(),
                'user_agent'  => substr($request->userAgent() ?? '', 0, 500),
            ]);

            \App\Tenancy\TenantContext::set(null);
        }

        return redirect()->away($decodedUrl);
    }

    /**
     * Validates that a URL is an absolute HTTP/HTTPS URL pointing to a
     * public internet address (blocks SSRF / open-redirect to internal IPs).
     */
    private function isSafeUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);

        // Must be http or https
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
            return false;
        }

        $host = strtolower($parsed['host'] ?? '');

        // Block localhost and loopback
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'])) {
            return false;
        }

        // Block private/link-local IP ranges (SSRF protection)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (
                filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ) {
                return false;
            }
        }

        return true;
    }
}



