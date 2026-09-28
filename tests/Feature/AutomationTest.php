<?php

use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;

/**
 * Automations — CRUD, step-graph reconciliation and input allow-listing.
 *
 * The original version of this file relied on `User::first()` against whatever
 * happened to be in the developer's database and skipped itself when empty, so
 * it asserted nothing in CI.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();
});

it('creates an automation with its steps', function (): void {
    $this->post(route('automations.store'), [
        'name' => 'Welcome sequence',
        'trigger_type' => Automation::TRIGGER_SUBSCRIBED,
        'is_active' => '1',
        'steps' => [
            ['type' => AutomationStep::TYPE_WAIT, 'config' => ['amount' => 1, 'unit' => 'days']],
            ['type' => AutomationStep::TYPE_SEND_EMAIL, 'config' => ['campaign_id' => null]],
        ],
    ])->assertRedirect(route('automations.index'));

    $automation = Automation::where('name', 'Welcome sequence')->sole();

    expect($automation->tenant_id)->toBe($this->user->tenant_id)
        ->and($automation->is_active)->toBeTrue()
        ->and($automation->steps)->toHaveCount(2)
        ->and($automation->steps->pluck('type')->all())
        ->toBe([AutomationStep::TYPE_WAIT, AutomationStep::TYPE_SEND_EMAIL])
        ->and($automation->steps->pluck('order_index')->all())->toBe([0, 1]);
});

it('rejects unknown step types and triggers', function (): void {
    $this->post(route('automations.store'), [
        'name' => 'Bad',
        'trigger_type' => 'telepathy',
        'steps' => [['type' => 'launch_missiles']],
    ])->assertSessionHasErrors(['trigger_type', 'steps.0.type']);

    expect(Automation::count())->toBe(0);
});

it('keeps step identities stable across an update', function (): void {
    $automation = Automation::create([
        'name' => 'Drip',
        'trigger_type' => Automation::TRIGGER_SUBSCRIBED,
    ]);

    $step = $automation->steps()->create([
        'type' => AutomationStep::TYPE_WAIT,
        'config' => ['amount' => 1],
        'order_index' => 0,
    ]);

    $this->put(route('automations.update', $automation), [
        'name' => 'Drip v2',
        'trigger_type' => Automation::TRIGGER_SUBSCRIBED,
        'steps' => [
            ['id' => $step->id, 'type' => AutomationStep::TYPE_WAIT, 'config' => ['amount' => 3]],
            ['type' => AutomationStep::TYPE_SEND_EMAIL, 'config' => []],
        ],
    ])->assertRedirect(route('automations.index'));

    // Recreating the graph on every save used to orphan in-flight enrolments.
    expect(AutomationStep::find($step->id))->not->toBeNull()
        ->and(AutomationStep::find($step->id)->config)->toBe(['amount' => 3])
        ->and($automation->fresh()->steps)->toHaveCount(2);
});

it('creates a contact with tags', function (): void {
    $this->post(route('contacts.store'), [
        'email' => 'tagged@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'tags' => 'vip, new_lead',
    ])->assertRedirect();

    $contact = Contact::where('email', 'tagged@example.com')->sole();

    expect($contact->tags->pluck('name')->sort()->values()->all())->toBe(['new_lead', 'vip'])
        ->and(Tag::where('tenant_id', $this->user->tenant_id)->count())->toBe(2);
});

it('does not leak automations across tenants', function (): void {
    $automation = Automation::create([
        'name' => 'Private',
        'trigger_type' => Automation::TRIGGER_SUBSCRIBED,
    ]);

    $intruder = User::factory()->create();
    App\Tenancy\TenantContext::set($intruder->tenant);

    $this->actingAs($intruder)
        ->get(route('automations.edit', $automation->id))
        ->assertNotFound();
});
