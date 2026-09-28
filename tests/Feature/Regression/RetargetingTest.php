<?php

use App\Jobs\DispatchCampaignJob;
use App\Jobs\SendCampaignChunkJob;
use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Contact;
use App\Models\ContactList;
use Illuminate\Support\Facades\Queue;

/**
 * Regression: `retarget` told the operator "openers excluded" while the
 * exclusion was a TODO — the follow-up blasted the exact same audience.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();
    $this->list = ContactList::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $this->campaign = Campaign::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'list_id' => $this->list->id,
        'status' => Campaign::STATUS_DRAFT,
    ]);

    $this->opener = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $this->silent = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);

    $this->opener->lists()->attach($this->list->id);
    $this->silent->lists()->attach($this->list->id);

    CampaignEvent::create([
        'tenant_id' => $this->user->tenant_id,
        'campaign_id' => $this->campaign->id,
        'contact_id' => $this->opener->id,
        'type' => CampaignEvent::TYPE_OPEN,
    ]);
});

it('creates a retarget draft that records the exclusion', function (): void {
    $this->post(route('campaigns.retarget', $this->campaign))->assertRedirect();

    $retarget = Campaign::where('name', '[Retarget] '.$this->campaign->name)->sole();

    expect($retarget->status)->toBe(Campaign::STATUS_DRAFT)
        ->and($retarget->excludedOpenersCampaignId())->toBe($this->campaign->id);
});

it('actually skips contacts who opened the source campaign', function (): void {
    Queue::fake();

    $this->post(route('campaigns.retarget', $this->campaign));
    $retarget = Campaign::where('name', '[Retarget] '.$this->campaign->name)->sole();

    (new DispatchCampaignJob($retarget))->handle();

    Queue::assertPushed(SendCampaignChunkJob::class, function (SendCampaignChunkJob $job) {
        return $job->contactIds === [$this->silent->id];
    });

    expect($retarget->fresh()->recipients_count)->toBe(1);
});

it('refuses to dispatch a campaign twice', function (): void {
    Queue::fake();

    (new DispatchCampaignJob($this->campaign))->handle();
    expect($this->campaign->fresh()->status)->toBe(Campaign::STATUS_SENDING);

    // Second run must be a no-op: the status flip is atomic.
    (new DispatchCampaignJob($this->campaign->fresh()))->handle();

    Queue::assertPushed(SendCampaignChunkJob::class, 1);
});

it('refuses to send a campaign that is not a draft', function (): void {
    $this->campaign->update(['status' => Campaign::STATUS_SENDING]);

    $this->post(route('campaigns.dispatch', $this->campaign))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('refuses to send a campaign with no list', function (): void {
    $orphan = Campaign::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'list_id' => null,
        'status' => Campaign::STATUS_DRAFT,
    ]);

    $this->post(route('campaigns.dispatch', $orphan))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('marks an empty campaign as completed rather than stuck on sending', function (): void {
    Queue::fake();

    $emptyList = ContactList::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $campaign = Campaign::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'list_id' => $emptyList->id,
        'status' => Campaign::STATUS_DRAFT,
    ]);

    (new DispatchCampaignJob($campaign))->handle();

    expect($campaign->fresh()->status)->toBe(Campaign::STATUS_COMPLETED);
});
