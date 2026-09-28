<?php

use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use App\Jobs\DispatchCampaignJob;

test('a user can dispatch a campaign', function () {
    Queue::fake();

    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenant_id]);
    $campaign = Campaign::factory()->create([
        'tenant_id' => $user->tenant_id,
        'list_id' => $list->id,
        'status' => 'draft'
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.dispatch', $campaign))
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(DispatchCampaignJob::class, function ($job) use ($campaign) {
        return $job->campaign->id === $campaign->id;
    });
});
