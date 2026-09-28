<?php

use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\User;

/**
 * Regression: the builder's <select> options were hand-written and had drifted
 * from the server's allow-lists. It offered `link_clicked` / `page_visited`
 * (rejected) and `tag` (really `add_tag`/`remove_tag`), so choosing any of them
 * failed validation and the form silently did nothing.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();
});

it('only offers triggers the server accepts', function (): void {
    $html = $this->get(route('automations.create'))->assertOk()->getContent();

    preg_match('/<select[^>]*name="trigger_type".*?<\/select>/s', $html, $m);
    preg_match_all('/<option value="([^"]+)"/', $m[0] ?? '', $options);

    $offered = array_values(array_filter($options[1] ?? []));

    expect($offered)->not->toBeEmpty()
        ->and(array_diff($offered, Automation::TRIGGERS))->toBe([]);
});

it('only offers step types the server accepts', function (): void {
    $html = $this->get(route('automations.create'))->assertOk()->getContent();

    preg_match('/Select action….*?<\/select>/s', $html, $m);
    preg_match_all('/<option value="([^"]+)"/', $m[0] ?? '', $options);

    $offered = array_values(array_filter($options[1] ?? []));

    expect($offered)->not->toBeEmpty()
        ->and(array_diff($offered, AutomationStep::TYPES))->toBe([]);
});

it('saves every trigger the builder offers', function (string $trigger): void {
    $this->post(route('automations.store'), [
        'name' => "Flow for {$trigger}",
        'trigger_type' => $trigger,
        'is_active' => '1',
        'steps' => [['type' => AutomationStep::TYPE_WAIT, 'config' => ['amount' => 2, 'unit' => 'hours']]],
    ])->assertRedirect(route('automations.index'))->assertSessionHasNoErrors();

    expect(Automation::where('trigger_type', $trigger)->exists())->toBeTrue();
})->with(Automation::TRIGGERS);

it('saves every step type the builder offers', function (string $type): void {
    $campaign = Campaign::factory()->create(['tenant_id' => $this->user->tenant_id]);

    $config = match ($type) {
        AutomationStep::TYPE_WAIT => ['amount' => 3, 'unit' => 'days'],
        AutomationStep::TYPE_SEND_EMAIL => ['campaign_id' => $campaign->id],
        AutomationStep::TYPE_ADD_TAG, AutomationStep::TYPE_REMOVE_TAG => ['tag_name' => 'vip'],
        AutomationStep::TYPE_UPDATE_FIELD => ['field' => 'first_name', 'value' => 'Friend'],
        AutomationStep::TYPE_WEBHOOK => ['url' => 'https://example.com/hook'],
        AutomationStep::TYPE_CONDITION => ['subject' => 'tag', 'value' => 'vip'],
    };

    $this->post(route('automations.store'), [
        'name' => "Flow with {$type}",
        'trigger_type' => Automation::TRIGGER_SUBSCRIBED,
        'steps' => [['type' => $type, 'config' => $config]],
    ])->assertRedirect(route('automations.index'))->assertSessionHasNoErrors();

    $automation = Automation::where('name', "Flow with {$type}")->sole();

    expect($automation->steps)->toHaveCount(1)
        ->and($automation->steps->first()->type)->toBe($type);
})->with(AutomationStep::TYPES);

it('persists trigger configuration', function (): void {
    $list = ContactList::factory()->create(['tenant_id' => $this->user->tenant_id]);

    $this->post(route('automations.store'), [
        'name' => 'List welcome',
        'trigger_type' => Automation::TRIGGER_LIST_JOINED,
        'trigger_config' => ['list_id' => $list->id],
        'steps' => [['type' => AutomationStep::TYPE_WAIT, 'config' => ['amount' => 1, 'unit' => 'days']]],
    ])->assertSessionHasNoErrors();

    expect(Automation::sole()->trigger_config)->toBe(['list_id' => $list->id]);
});

it('rejects a campaign belonging to another workspace in a step', function (): void {
    $victim = User::factory()->create();
    $foreign = Campaign::factory()->create(['tenant_id' => $victim->tenant_id]);

    $this->post(route('automations.store'), [
        'name' => 'Cross tenant',
        'trigger_type' => Automation::TRIGGER_SUBSCRIBED,
        'steps' => [['type' => AutomationStep::TYPE_SEND_EMAIL, 'config' => ['campaign_id' => $foreign->id]]],
    ])->assertSessionHasErrors('steps.0.config.campaign_id');

    expect(Automation::count())->toBe(0);
});

it('rejects a wait longer than the allowed bound', function (): void {
    $this->post(route('automations.store'), [
        'name' => 'Forever',
        'trigger_type' => Automation::TRIGGER_SUBSCRIBED,
        'steps' => [['type' => AutomationStep::TYPE_WAIT, 'config' => ['amount' => 9999, 'unit' => 'days']]],
    ])->assertSessionHasErrors('steps.0.config.amount');
});

it('keeps the automation inactive when the toggle is off', function (): void {
    $this->post(route('automations.store'), [
        'name' => 'Draft flow',
        'trigger_type' => Automation::TRIGGER_SUBSCRIBED,
        'is_active' => '0',
        'steps' => [['type' => AutomationStep::TYPE_WAIT, 'config' => ['amount' => 1, 'unit' => 'days']]],
    ])->assertSessionHasNoErrors();

    expect(Automation::sole()->is_active)->toBeFalse();
});

it('reorders steps without recreating them', function (): void {
    $automation = Automation::create(['name' => 'Seq', 'trigger_type' => Automation::TRIGGER_SUBSCRIBED]);
    $first = $automation->steps()->create(['type' => AutomationStep::TYPE_WAIT, 'config' => ['amount' => 1], 'order_index' => 0]);
    $second = $automation->steps()->create(['type' => AutomationStep::TYPE_ADD_TAG, 'config' => ['tag_name' => 'a'], 'order_index' => 1]);

    $this->put(route('automations.update', $automation), [
        'name' => 'Seq',
        'trigger_type' => Automation::TRIGGER_SUBSCRIBED,
        'steps' => [
            ['id' => $second->id, 'type' => AutomationStep::TYPE_ADD_TAG, 'config' => ['tag_name' => 'a']],
            ['id' => $first->id, 'type' => AutomationStep::TYPE_WAIT, 'config' => ['amount' => 1, 'unit' => 'days']],
        ],
    ])->assertSessionHasNoErrors();

    expect($automation->fresh()->steps->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and(AutomationStep::count())->toBe(2);
});
