<?php

use App\Automations\AutomationEngine;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactAutomation;
use App\Models\ContactList;
use App\Models\LeadCaptureForm;
use App\Models\SmtpAccount;
use App\Models\SuppressionEntry;
use App\Models\Tag;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * The automation runtime. Until now the builder saved a step graph that nothing
 * ever executed — these tests are the proof that it runs.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();
    $this->engine = app(AutomationEngine::class);

    $this->makeAutomation = function (string $trigger, array $steps, array $config = []): Automation {
        $automation = Automation::create([
            'name' => 'Flow '.uniqid(),
            'trigger_type' => $trigger,
            'trigger_config' => $config ?: null,
            'is_active' => true,
        ]);

        foreach (array_values($steps) as $i => $step) {
            $automation->steps()->create([
                'type' => $step[0],
                'config' => $step[1] ?? [],
                'order_index' => $i,
            ]);
        }

        return $automation->load('steps');
    };
});

/*
|--------------------------------------------------------------------------
| Enrolment
|--------------------------------------------------------------------------
*/

it('enrols a contact when its trigger fires', function (): void {
    $automation = ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [[AutomationStep::TYPE_WAIT, ['amount' => 1, 'unit' => 'days']]]);
    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);

    expect($this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact))->toBe(1);

    $enrolment = ContactAutomation::sole();

    expect($enrolment->automation_id)->toBe($automation->id)
        ->and($enrolment->status)->toBe(ContactAutomation::STATUS_RUNNING)
        ->and($enrolment->current_step_id)->toBe($automation->steps->first()->id);
});

it('never enrols the same contact twice', function (): void {
    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [[AutomationStep::TYPE_WAIT, ['amount' => 1]]]);
    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);

    // Re-entry on every trigger would turn a "tag added" flow into a mail loop.
    $this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact);
    $this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact);

    expect(ContactAutomation::count())->toBe(1);
});

it('ignores inactive automations and non-mailable contacts', function (): void {
    $automation = ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [[AutomationStep::TYPE_WAIT, ['amount' => 1]]]);
    $automation->update(['is_active' => false]);

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);
    expect($this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact))->toBe(0);

    $automation->update(['is_active' => true]);

    // Enrolling an unsubscribed contact only queues mail that must be dropped.
    $optedOut = Contact::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'status' => Contact::STATUS_UNSUBSCRIBED,
    ]);
    expect($this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $optedOut))->toBe(0);
});

it('honours trigger configuration', function (): void {
    ($this->makeAutomation)(Automation::TRIGGER_TAG_ADDED, [[AutomationStep::TYPE_WAIT, ['amount' => 1]]], ['tag' => 'vip']);
    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);

    expect($this->engine->trigger(Automation::TRIGGER_TAG_ADDED, $contact, ['tag' => 'newsletter']))->toBe(0)
        ->and($this->engine->trigger(Automation::TRIGGER_TAG_ADDED, $contact, ['tag' => 'vip']))->toBe(1);
});

it('never enrols a contact from another workspace', function (): void {
    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [[AutomationStep::TYPE_WAIT, ['amount' => 1]]]);

    $outsider = Contact::factory()->create(['tenant_id' => App\Models\User::factory()->create()->tenant_id]);

    expect($this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $outsider))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Execution
|--------------------------------------------------------------------------
*/

it('walks the graph step by step', function (): void {
    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [
        [AutomationStep::TYPE_ADD_TAG, ['tag_name' => 'welcomed']],
        [AutomationStep::TYPE_UPDATE_FIELD, ['field' => 'first_name', 'value' => 'Friend']],
    ]);

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id, 'first_name' => 'Ada']);
    $this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact);

    $this->engine->processDue();   // step 1
    expect($contact->fresh()->tags->pluck('name')->all())->toBe(['welcomed']);

    $this->engine->processDue();   // step 2
    expect($contact->fresh()->first_name)->toBe('Friend');

    $this->engine->processDue();   // graph exhausted
    expect(ContactAutomation::sole()->status)->toBe(ContactAutomation::STATUS_COMPLETED);
});

it('parks a wait step until it is due', function (): void {
    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [
        [AutomationStep::TYPE_WAIT, ['amount' => 2, 'unit' => 'days']],
        [AutomationStep::TYPE_ADD_TAG, ['tag_name' => 'later']],
    ]);

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact);

    $this->engine->processDue();

    expect(ContactAutomation::sole()->execute_next_at->isAfter(now()->addDay()))->toBeTrue();

    // Nothing happens while it is parked — that is the whole point.
    $this->engine->processDue();
    expect($contact->fresh()->tags)->toHaveCount(0);

    $this->travel(3)->days();
    $this->engine->processDue();   // the deferred step 2 runs

    expect($contact->fresh()->tags->pluck('name')->all())->toBe(['later']);
});

it('sends a campaign as an automation email', function (): void {
    Mail::fake();

    $relay = SmtpAccount::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $campaign = Campaign::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'subject' => 'Welcome [Name]',
        'editor_html' => '<p>Hello [Name]</p>',
    ]);
    $campaign->smtpAccounts()->attach($relay->id);

    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [[AutomationStep::TYPE_SEND_EMAIL, ['campaign_id' => $campaign->id]]]);

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id, 'first_name' => 'Ada']);
    $this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact);
    $this->engine->processDue();

    Mail::assertSent(App\Mail\CampaignEmail::class, function ($mail) use ($contact) {
        // Personalisation must work exactly as it does on the broadcast path —
        // an automation email is not second-class.
        return $mail->hasTo($contact->email) && $mail->campaignSubject === 'Welcome Ada';
    });

    expect($relay->fresh()->sent_today)->toBe(1);
});

it('skips a suppressed address without failing the journey', function (): void {
    Mail::fake();

    $relay = SmtpAccount::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $campaign = Campaign::factory()->create(['tenant_id' => $this->user->tenant_id, 'editor_html' => '<p>Hi</p>']);
    $campaign->smtpAccounts()->attach($relay->id);

    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [[AutomationStep::TYPE_SEND_EMAIL, ['campaign_id' => $campaign->id]]]);

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);
    SuppressionEntry::suppress($contact->email, SuppressionEntry::REASON_HARD_BOUNCE, $this->user->tenant_id);

    $this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact);
    $this->engine->processDue();

    Mail::assertNothingSent();
    expect(ContactAutomation::sole()->status)->not->toBe(ContactAutomation::STATUS_PAUSED);
});

it('drops a contact who unsubscribes mid-journey', function (): void {
    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [
        [AutomationStep::TYPE_WAIT, ['amount' => 1, 'unit' => 'minutes']],
        [AutomationStep::TYPE_ADD_TAG, ['tag_name' => 'should-not-apply']],
    ]);

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact);
    $this->engine->processDue();

    $contact->forceFill(['status' => Contact::STATUS_UNSUBSCRIBED])->save();

    $this->travel(2)->minutes();
    $this->engine->processDue();

    expect(ContactAutomation::sole()->status)->toBe(ContactAutomation::STATUS_COMPLETED)
        ->and($contact->fresh()->tags)->toHaveCount(0);
});

it('ends the journey when a condition is not met', function (): void {
    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [
        [AutomationStep::TYPE_CONDITION, ['subject' => 'tag', 'value' => 'vip']],
        [AutomationStep::TYPE_ADD_TAG, ['tag_name' => 'vip-only-reward']],
    ]);

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact);
    $this->engine->processDue();

    // "Only continue if still VIP" is the common intent; carrying on silently
    // would reward the wrong people.
    expect(ContactAutomation::sole()->status)->toBe(ContactAutomation::STATUS_COMPLETED)
        ->and($contact->fresh()->tags)->toHaveCount(0);
});

it('continues past a condition that is met', function (): void {
    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [
        [AutomationStep::TYPE_CONDITION, ['subject' => 'tag', 'value' => 'vip']],
        [AutomationStep::TYPE_ADD_TAG, ['tag_name' => 'rewarded']],
    ]);

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $contact->tags()->attach(Tag::create(['tenant_id' => $this->user->tenant_id, 'name' => 'vip'])->id);

    $this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact);
    $this->engine->processDue();
    $this->engine->processDue();

    expect($contact->fresh()->tags->pluck('name')->sort()->values()->all())->toBe(['rewarded', 'vip']);
});

it('posts a webhook but refuses private targets', function (): void {
    Http::fake(['hooks.example.com/*' => Http::response(['ok' => true])]);

    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [[AutomationStep::TYPE_WEBHOOK, ['url' => 'https://hooks.example.com/new']]]);
    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);

    $this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact);
    $this->engine->processDue();

    Http::assertSent(fn ($request) => $request['contact']['email'] === $contact->email);

    // An automation must not become an SSRF primitive aimed at cloud metadata.
    $bad = ($this->makeAutomation)(Automation::TRIGGER_TAG_ADDED, [[AutomationStep::TYPE_WEBHOOK, ['url' => 'http://169.254.169.254/latest/meta-data/']]]);
    $victim = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);

    $this->engine->enrol($bad, $victim);
    $this->engine->processDue();

    expect(ContactAutomation::where('automation_id', $bad->id)->sole()->status)
        ->toBe(ContactAutomation::STATUS_PAUSED);
});

it('pauses rather than retrying forever when a step is broken', function (): void {
    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [[AutomationStep::TYPE_SEND_EMAIL, ['campaign_id' => (string) Illuminate\Support\Str::uuid7()]]]);

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact);
    $this->engine->processDue();

    // A deleted campaign will not fix itself; hot-looping helps nobody.
    expect(ContactAutomation::sole()->status)->toBe(ContactAutomation::STATUS_PAUSED)
        ->and(ContactAutomation::sole()->execute_next_at)->toBeNull();
});

it('claims each enrolment once so concurrent workers cannot double-send', function (): void {
    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [[AutomationStep::TYPE_ADD_TAG, ['tag_name' => 'once']]]);
    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $this->engine->trigger(Automation::TRIGGER_SUBSCRIBED, $contact);

    $id = ContactAutomation::sole()->id;

    // One step, so the first pass runs it and finishes the journey; the claim
    // pushes execute_next_at forward, so a second worker finds nothing to do.
    expect($this->engine->processOne($id))->toBe('completed')
        ->and($this->engine->processOne($id))->toBe('skipped');
});

/*
|--------------------------------------------------------------------------
| Trigger wiring
|--------------------------------------------------------------------------
*/

it('fires on a contact added through the UI', function (): void {
    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [[AutomationStep::TYPE_ADD_TAG, ['tag_name' => 'welcomed']]]);

    $this->post(route('contacts.store'), ['email' => 'new@example.com'])->assertRedirect();

    expect(ContactAutomation::count())->toBe(1);
});

it('fires on a public lead capture', function (): void {
    $list = ContactList::factory()->create(['tenant_id' => $this->user->tenant_id]);
    ($this->makeAutomation)(Automation::TRIGGER_LIST_JOINED, [[AutomationStep::TYPE_ADD_TAG, ['tag_name' => 'from-form']]], ['list_id' => $list->id]);

    $form = LeadCaptureForm::factory()->create(['tenant_id' => $this->user->tenant_id, 'list_id' => $list->id]);

    $this->postJson('/api/v1/leads/capture', ['form_key' => $form->public_key, 'email' => 'lead@example.com'])
        ->assertCreated();

    expect(ContactAutomation::withoutGlobalScopes()->count())->toBe(1);
});

it('fires on an open and a click', function (): void {
    $campaign = Campaign::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);

    ($this->makeAutomation)(Automation::TRIGGER_CAMPAIGN_OPENED, [[AutomationStep::TYPE_ADD_TAG, ['tag_name' => 'opener']]]);
    ($this->makeAutomation)(Automation::TRIGGER_CAMPAIGN_CLICKED, [[AutomationStep::TYPE_ADD_TAG, ['tag_name' => 'clicker']]]);

    $this->get(route('tracking.open', ['campaign' => $campaign->id, 'contact' => $contact->id]))->assertOk();
    $this->get(route('tracking.click', [
        'campaign' => $campaign->id, 'contact' => $contact->id, 'url' => base64_encode('https://example.com'),
    ]))->assertRedirect();

    expect(ContactAutomation::withoutGlobalScopes()->count())->toBe(2);
});

it('does not enrol bulk-imported contacts', function (): void {
    ($this->makeAutomation)(Automation::TRIGGER_SUBSCRIBED, [[AutomationStep::TYPE_ADD_TAG, ['tag_name' => 'welcomed']]]);

    $this->post(route('contacts.import'), [
        'csv_file' => Illuminate\Http\UploadedFile::fake()->createWithContent('c.csv', "email\na@example.com\nb@example.com"),
    ])->assertRedirect();

    // Dropping 400k imported contacts into a welcome sequence is almost never
    // what the operator meant, and there is no undo.
    expect(Contact::count())->toBe(2)
        ->and(ContactAutomation::count())->toBe(0);
});
