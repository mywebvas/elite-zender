<?php

use App\Billing\PlanGate;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SmtpAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivationChecklist;

/**
 * Activation: getting a new workspace from "signed up" to "has sent".
 *
 * The old first-run wizard was a three-step Alpine mock. `testSmtp()` waited
 * a second and toasted "SMTP Connected Successfully"; `importContacts()`
 * waited 1.5 seconds and toasted "Contacts imported". Neither issued a single
 * request — no relay was created, no contact imported, nothing persisted. A
 * new customer was congratulated three times and landed on an empty
 * dashboard, unable to send, with no idea why.
 *
 * Every tick is now derived from real state, and the send gate that protects
 * everybody's deliverability is asserted alongside it.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();
    $this->tenant = $this->user->tenant;
});

it('reports nothing done on a brand-new workspace except the parts that are', function (): void {
    $summary = app(ActivationChecklist::class)->summary();

    // The factory verifies email, so that one step starts ticked.
    expect($summary['total'])->toBe(5)
        ->and($summary['completed'])->toBe(1)
        ->and($summary['complete'])->toBeFalse()
        ->and($summary['next']['key'])->toBe('smtp');
});

it('ticks each step off against real workspace state, never a stored flag', function (): void {
    SmtpAccount::factory()->create(['tenant_id' => $this->tenant->id]);
    expect(app(ActivationChecklist::class)->summary()['next']['key'])->toBe('contacts');

    Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    expect(app(ActivationChecklist::class)->summary()['next']['key'])->toBe('campaign');

    $campaign = Campaign::factory()->create(['tenant_id' => $this->tenant->id]);
    expect(app(ActivationChecklist::class)->summary()['next']['key'])->toBe('sent');

    $campaign->forceFill(['sent_count' => 1])->save();

    $summary = app(ActivationChecklist::class)->summary();

    expect($summary['complete'])->toBeTrue()
        ->and($summary['percent'])->toBe(100)
        ->and($summary['next'])->toBeNull();
});

it('shows the real checklist on the onboarding page instead of a scripted demo', function (): void {
    $response = $this->get(route('onboarding'));

    $response->assertOk()
        ->assertSee('Connect an SMTP relay')
        ->assertSee('Add a relay')
        // The mock wizard's fake success messages must never come back.
        ->assertDontSee('SMTP Connected Successfully')
        ->assertDontSee('Contacts imported');
});

it('links every unfinished step to the screen that actually does the job', function (): void {
    $steps = app(ActivationChecklist::class)->steps();

    $urls = collect($steps)->pluck('url', 'key');

    expect($urls['smtp'])->toBe(route('smtp-accounts.index'))
        ->and($urls['contacts'])->toBe(route('contacts.index'))
        ->and($urls['campaign'])->toBe(route('campaigns.create'))
        ->and($urls['verify'])->toBe(route('verification.notice'));
});

it('leads the dashboard with the next step until the first campaign is out', function (): void {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Finish setting up')
        ->assertSee('Connect an SMTP relay');
});

it('drops the setup card once the workspace has sent something', function (): void {
    SmtpAccount::factory()->create(['tenant_id' => $this->tenant->id]);
    Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'sent_count' => 5]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Finish setting up');
});

/*
|--------------------------------------------------------------------------
| Email verification: a send gate, not a login wall
|--------------------------------------------------------------------------
*/

it('blocks sending until somebody in the workspace has confirmed their address', function (): void {
    $unverified = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $unverified->id, 'email_verified_at' => null]);

    $gate = app(PlanGate::class);

    expect($gate->awaitingEmailVerification($unverified))->toBeTrue()
        ->and($gate->canSend($unverified))->toBeFalse()
        ->and($gate->sendBlockReason($unverified))->toContain('Confirm your email');
});

it('unblocks the moment one member verifies', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => null]);
    User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);

    expect(app(PlanGate::class)->awaitingEmailVerification($tenant))->toBeFalse();
});

it('does not treat an empty workspace as unverified', function (): void {
    // No users at all cannot have initiated anything; blocking it would break
    // system jobs and fixtures for no security gain.
    expect(app(PlanGate::class)->awaitingEmailVerification(Tenant::factory()->create()))->toBeFalse();
});

it('refuses to dispatch a campaign from an unconfirmed workspace', function (): void {
    $this->user->forceFill(['email_verified_at' => null])->save();

    $list = ContactList::factory()->create(['tenant_id' => $this->tenant->id]);
    $campaign = Campaign::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Campaign::STATUS_DRAFT,
        'list_id' => $list->id,
    ]);

    $this->post(route('campaigns.dispatch', $campaign))
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect($campaign->fresh()->status)->toBe(Campaign::STATUS_DRAFT);
});

it('keeps the rest of the product open to an unconfirmed workspace', function (): void {
    $this->user->forceFill(['email_verified_at' => null])->save();

    // Verification gates the send button, nothing else. Locking someone out
    // of setup until they click a link they may never receive is how a
    // signup becomes a refund request.
    $this->get(route('dashboard'))->assertOk();
    $this->get(route('contacts.index'))->assertOk();
    $this->get(route('campaigns.create'))->assertOk();
    $this->get(route('smtp-accounts.index'))->assertOk();
});

it('tells an unconfirmed workspace exactly what to do, on every page', function (): void {
    $this->user->forceFill(['email_verified_at' => null])->save();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Confirm your email address to unlock sending')
        ->assertSee('Confirm email');
});

it('offers a one-click resend on the verification page', function (): void {
    $this->user->forceFill(['email_verified_at' => null])->save();

    $this->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('Send the link again')
        ->assertSee($this->user->email);
});
