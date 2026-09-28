<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Contact;
use App\Models\SuppressionEntry;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Opt-out endpoint. The URL is signed (see App\Support\UnsubscribeLink).
 *
 * GET renders a confirmation page and changes nothing. That is deliberate:
 * corporate mail gateways and link scanners pre-fetch every URL in a message,
 * so a GET that mutates state silently unsubscribes entire audiences. Only the
 * POST — either the confirmation button or an RFC 8058 one-click request —
 * actually opts the contact out.
 */
class UnsubscribeController extends Controller
{
    public function __invoke(Request $request, string $campaign, string $contact): View|JsonResponse|Response
    {
        [$campaignModel, $contactModel] = $this->resolve($campaign, $contact);

        if ($request->isMethod('get')) {
            return view('unsubscribe.confirm', [
                'campaign' => $campaignModel,
                'contact' => $contactModel,
                'alreadyUnsubscribed' => $contactModel->status === Contact::STATUS_UNSUBSCRIBED,
                'actionUrl' => $request->fullUrl(),
            ]);
        }

        TenantContext::run(Tenant::find($campaignModel->tenant_id), function () use ($campaignModel, $contactModel): void {
            if ($contactModel->status !== Contact::STATUS_UNSUBSCRIBED) {
                $contactModel->forceFill(['status' => Contact::STATUS_UNSUBSCRIBED])->save();

                SuppressionEntry::suppress(
                    $contactModel->email,
                    SuppressionEntry::REASON_UNSUBSCRIBE,
                    $campaignModel->tenant_id,
                );

                CampaignEvent::firstOrCreate([
                    'campaign_id' => $campaignModel->getKey(),
                    'contact_id' => $contactModel->getKey(),
                    'type' => CampaignEvent::TYPE_UNSUBSCRIBE,
                ], [
                    'tenant_id' => $campaignModel->tenant_id,
                ]);
            }
        });

        // RFC 8058 clients expect a bare 200 with no body requirements.
        if ($request->expectsJson() || $request->input('List-Unsubscribe') === 'One-Click') {
            return response()->json(['message' => 'Unsubscribed successfully.']);
        }

        return response()->view('unsubscribe.done', ['contact' => $contactModel]);
    }

    /**
     * Resolve both models without the tenant scope (the visitor is a guest)
     * and prove they belong together — a signed link for campaign A must not
     * be replayable against a contact from tenant B.
     *
     * @return array{0: Campaign, 1: Contact}
     */
    private function resolve(string $campaignId, string $contactId): array
    {
        $campaign = Campaign::withoutGlobalScopes()->find($campaignId);
        $contact = Contact::withoutGlobalScopes()->find($contactId);

        abort_if($campaign === null || $contact === null, 404);
        abort_if($campaign->tenant_id !== $contact->tenant_id, 404);

        return [$campaign, $contact];
    }
}
