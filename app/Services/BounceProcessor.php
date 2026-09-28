<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Contact;
use App\Models\SuppressionEntry;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Applies a classified bounce to the audience: records the event, marks the
 * contact and (for permanent failures) writes a suppression entry.
 */
final class BounceProcessor
{
    public function __construct(
        private readonly BounceClassifier $classifier,
    ) {}

    /**
     * @return array{classification: string, contact_id: string|null}
     */
    public function process(string $tenantId, string $rawMessage, ?string $recipient = null, ?string $campaignId = null): array
    {
        $classification = $this->classifier->classify($rawMessage);
        $email = $recipient ?? $this->classifier->extractRecipient($rawMessage);

        if ($email === null) {
            return ['classification' => $classification, 'contact_id' => null];
        }

        return DB::transaction(function () use ($tenantId, $email, $classification, $campaignId): array {
            $contact = Contact::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('email', $email)
                ->first();

            if ($classification === BounceClassifier::SOFT) {
                // Soft failures are recorded for deliverability reporting but
                // never remove the subscriber.
                $this->recordEvent($tenantId, $contact, $campaignId, CampaignEvent::TYPE_BOUNCE);

                return ['classification' => $classification, 'contact_id' => $contact?->getKey()];
            }

            $status = $classification === BounceClassifier::COMPLAINT
                ? Contact::STATUS_COMPLAINED
                : Contact::STATUS_BOUNCED;

            $contact?->forceFill(['status' => $status])->save();

            SuppressionEntry::suppress(
                $email,
                $classification === BounceClassifier::COMPLAINT
                    ? SuppressionEntry::REASON_COMPLAINT
                    : SuppressionEntry::REASON_HARD_BOUNCE,
                $tenantId,
            );

            $this->recordEvent(
                $tenantId,
                $contact,
                $campaignId,
                $classification === BounceClassifier::COMPLAINT
                    ? CampaignEvent::TYPE_COMPLAINT
                    : CampaignEvent::TYPE_BOUNCE,
            );

            return ['classification' => $classification, 'contact_id' => $contact?->getKey()];
        });
    }

    private function recordEvent(string $tenantId, ?Contact $contact, ?string $campaignId, string $type): void
    {
        if ($contact === null || $campaignId === null) {
            return;
        }

        $exists = Campaign::withoutGlobalScopes()->whereKey($campaignId)->where('tenant_id', $tenantId)->exists();

        if (! $exists) {
            return;
        }

        TenantContext::runWithoutTenant(function () use ($tenantId, $contact, $campaignId, $type): void {
            CampaignEvent::withoutGlobalScopes()->firstOrCreate([
                'campaign_id' => $campaignId,
                'contact_id' => $contact->getKey(),
                'type' => $type,
            ], [
                'tenant_id' => $tenantId,
            ]);
        });
    }
}
