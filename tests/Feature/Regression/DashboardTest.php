<?php

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Contact;
use App\Models\SmtpAccount;

/**
 * Regression: the dashboard ran its own Eloquent queries inside the Blade
 * template and rendered a hard-coded em-dash for every KPI, plus a static
 * "0.0% — Excellent" bounce rate regardless of reality.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();
});

it('renders real figures rather than placeholders', function (): void {
    $campaign = Campaign::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'name' => 'Spring launch',
        'sent_count' => 200,
        'recipients_count' => 200,
    ]);

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);

    foreach ([CampaignEvent::TYPE_OPEN, CampaignEvent::TYPE_CLICK] as $type) {
        CampaignEvent::create([
            'tenant_id' => $this->user->tenant_id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'type' => $type,
        ]);
    }

    SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'name' => 'Primary relay',
        'sent_today' => 40,
        'daily_limit' => 100,
    ]);

    $response = $this->get(route('dashboard'))->assertOk();

    $response->assertSee('Spring launch')
        ->assertSee('Primary relay')
        ->assertSee('200')            // emails sent
        ->assertSee('0.5%')           // 1 open / 200 sent
        ->assertDontSee('None configured');
});

it('does not leak another workspace figures', function (): void {
    $other = App\Models\User::factory()->create();

    Campaign::factory()->create([
        'tenant_id' => $other->tenant_id,
        'name' => 'Competitor campaign',
        'sent_count' => 5000,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Competitor campaign')
        ->assertDontSee('5,000');
});

it('shows an honest empty state for a brand new workspace', function (): void {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No campaigns yet')
        ->assertSee('None configured');
});

it('surfaces flashed validation errors to the user', function (): void {
    $campaign = Campaign::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'status' => Campaign::STATUS_SENDING,
    ]);

    // Controllers reject invalid actions with withErrors(); nothing in the
    // layout rendered $errors, so a refused action looked like a no-op.
    $this->from(route('campaigns.show', $campaign))
        ->post(route('campaigns.dispatch', $campaign))
        ->assertRedirect(route('campaigns.show', $campaign))
        ->assertSessionHasErrors();

    $this->followingRedirects()
        ->from(route('campaigns.show', $campaign))
        ->post(route('campaigns.dispatch', $campaign))
        ->assertOk()
        ->assertSee('Only draft campaigns can be sent.', escape: false);
});

it('offers a skip link on the authenticated shell', function (): void {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Skip to main content')
        ->assertSee('id="main-content"', escape: false);
});
