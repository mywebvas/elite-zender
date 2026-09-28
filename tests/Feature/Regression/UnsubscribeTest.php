<?php

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Contact;
use App\Models\SuppressionEntry;
use App\Models\User;
use App\Support\UnsubscribeLink;

/**
 * Unsubscribe is both a legal obligation and a deliverability control.
 *
 * Regressions covered here:
 *  - the link was unsigned, so any pair of UUIDs opted a stranger out;
 *  - GET mutated state, so corporate link scanners silently unsubscribed
 *    entire audiences on delivery;
 *  - the RFC 8058 one-click POST sat behind CSRF and could never succeed.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->campaign = Campaign::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $this->contact = Contact::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'status' => Contact::STATUS_ACTIVE,
    ]);
    $this->link = UnsubscribeLink::for($this->campaign, $this->contact);
});

it('rejects an unsigned unsubscribe link', function (): void {
    $this->get(route('unsubscribe', ['campaign' => $this->campaign->id, 'contact' => $this->contact->id]))
        ->assertForbidden();

    expect($this->contact->fresh()->status)->toBe(Contact::STATUS_ACTIVE);
});

it('rejects a link whose signature was tampered with', function (): void {
    $this->get($this->link.'x')->assertForbidden();
});

it('does not unsubscribe on GET — it only shows a confirmation', function (): void {
    $this->get($this->link)
        ->assertOk()
        ->assertSee('Unsubscribe from this list?', escape: false);

    expect($this->contact->fresh()->status)->toBe(Contact::STATUS_ACTIVE);
});

it('unsubscribes on POST and records a suppression entry', function (): void {
    $this->post($this->link)->assertOk();

    expect($this->contact->fresh()->status)->toBe(Contact::STATUS_UNSUBSCRIBED)
        ->and(SuppressionEntry::suppresses($this->contact->email, $this->user->tenant_id))->toBeTrue();

    $this->assertDatabaseHas('campaign_events', [
        'campaign_id' => $this->campaign->id,
        'contact_id' => $this->contact->id,
        'type' => CampaignEvent::TYPE_UNSUBSCRIBE,
    ]);
});

it('accepts the RFC 8058 one-click POST without a CSRF token', function (): void {
    // No session, no token — exactly what a mail client sends.
    $this->post($this->link, ['List-Unsubscribe' => 'One-Click'], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('message', 'Unsubscribed successfully.');

    expect($this->contact->fresh()->status)->toBe(Contact::STATUS_UNSUBSCRIBED);
});

it('is idempotent', function (): void {
    $this->post($this->link)->assertOk();
    $this->post($this->link)->assertOk();

    expect(CampaignEvent::withoutGlobalScopes()
        ->where('contact_id', $this->contact->id)
        ->where('type', CampaignEvent::TYPE_UNSUBSCRIBE)
        ->count())->toBe(1);
});

it('refuses a campaign and contact from different workspaces', function (): void {
    $other = User::factory()->create();
    $foreignContact = Contact::factory()->create(['tenant_id' => $other->tenant_id]);

    $link = Illuminate\Support\Facades\URL::signedRoute('unsubscribe', [
        'campaign' => $this->campaign->id,
        'contact' => $foreignContact->id,
    ]);

    $this->post($link)->assertNotFound();

    expect($foreignContact->fresh()->status)->toBe(Contact::STATUS_ACTIVE);
});
