<?php

use App\Jobs\DispatchCampaignJob;
use App\Jobs\SendCampaignChunkJob;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactList;
use Illuminate\Support\Facades\Queue;

test('it chunks contacts and dispatches send jobs', function () {
    Queue::fake();

    $user = \App\Models\User::factory()->create();
    $tenantId = $user->tenant_id;

    $list = ContactList::factory()->create(['tenant_id' => $tenantId]);
    $campaign = Campaign::factory()->create([
        'tenant_id' => $tenantId,
        'list_id' => $list->id,
        'status' => 'draft'
    ]);

    // Create 3 active contacts, 1 inactive
    Contact::factory()->count(3)->create([
        'tenant_id' => $tenantId,
        'status' => 'active'
    ])->each(fn($c) => $c->lists()->syncWithoutDetaching([$list->id]));

    Contact::factory()->create([
        'tenant_id' => $tenantId,
        'status' => 'unsubscribed'
    ])->each(fn($c) => $c->lists()->syncWithoutDetaching([$list->id]));

    $job = new DispatchCampaignJob($campaign);
    $job->handle();

    expect($campaign->fresh()->status)->toBe('sending');

    Queue::assertPushed(SendCampaignChunkJob::class, function ($job) use ($campaign) {
        return $job->campaign->id === $campaign->id && count($job->contactIds) === 3;
    });
});
