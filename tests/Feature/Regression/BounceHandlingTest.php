<?php

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Contact;
use App\Models\SuppressionEntry;
use App\Services\BounceClassifier;
use App\Services\BounceProcessor;

/**
 * CheckImapBouncesJob used to be a stub that logged "Scanning for bounces..."
 * and returned. Nothing classified a DSN, nothing suppressed an address.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();
    $this->processor = app(BounceProcessor::class);
});

it('classifies a permanent failure as hard', function (string $dsn): void {
    expect((new BounceClassifier)->classify($dsn))->toBe(BounceClassifier::HARD);
})->with([
    "Final-Recipient: rfc822; a@b.com\nStatus: 5.1.1\n",
    'The email account that you tried to reach does not exist. user unknown',
    'Recipient address rejected: User unknown in virtual mailbox table',
]);

it('classifies a temporary failure as soft', function (string $dsn): void {
    expect((new BounceClassifier)->classify($dsn))->toBe(BounceClassifier::SOFT);
})->with([
    "Status: 4.2.2\n",
    'The recipient mailbox full, message deferred',
    'Some entirely unrecognised failure text',
]);

it('detects a feedback-loop complaint', function (): void {
    expect((new BounceClassifier)->classify("Feedback-Type: abuse\nUser-Agent: X"))
        ->toBe(BounceClassifier::COMPLAINT);
});

it('suppresses and marks a contact on a hard bounce', function (): void {
    $campaign = Campaign::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $contact = Contact::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'email' => 'dead@example.com',
    ]);

    $this->processor->process(
        $this->user->tenant_id,
        "Final-Recipient: rfc822; dead@example.com\nStatus: 5.1.1\n",
        campaignId: $campaign->id,
    );

    expect($contact->fresh()->status)->toBe(Contact::STATUS_BOUNCED)
        ->and(SuppressionEntry::suppresses('dead@example.com', $this->user->tenant_id))->toBeTrue();

    $this->assertDatabaseHas('campaign_events', [
        'contact_id' => $contact->id,
        'type' => CampaignEvent::TYPE_BOUNCE,
    ]);
});

it('leaves a subscriber alone on a soft bounce', function (): void {
    $contact = Contact::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'email' => 'busy@example.com',
    ]);

    $this->processor->process(
        $this->user->tenant_id,
        "Final-Recipient: rfc822; busy@example.com\nStatus: 4.2.2 mailbox full\n",
    );

    expect($contact->fresh()->status)->toBe(Contact::STATUS_ACTIVE)
        ->and(SuppressionEntry::suppresses('busy@example.com', $this->user->tenant_id))->toBeFalse();
});

it('stores suppression as a keyed hash, never the address', function (): void {
    SuppressionEntry::suppress('private@example.com', SuppressionEntry::REASON_MANUAL, $this->user->tenant_id);

    $row = Illuminate\Support\Facades\DB::table('suppression_entries')->first();

    expect($row->email_hash)->not->toContain('private@example.com')
        ->and(strlen($row->email_hash))->toBe(64);
});
