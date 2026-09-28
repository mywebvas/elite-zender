<?php

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\User;

test('pixel endpoint logs open and returns gif', function (): void {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create(['tenant_id' => $user->tenant_id]);
    $contact = Contact::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->get(route('tracking.open', ['campaign' => $campaign->id, 'contact' => $contact->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    $this->assertDatabaseHas('campaign_events', [
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'type' => 'open',
    ]);
});

test('click endpoint logs click and redirects', function (): void {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create(['tenant_id' => $user->tenant_id]);
    $contact = Contact::factory()->create(['tenant_id' => $user->tenant_id]);

    $url = 'https://example.com/promo';
    $encodedUrl = base64_encode($url);

    $this->get(route('tracking.click', ['campaign' => $campaign->id, 'contact' => $contact->id, 'url' => $encodedUrl]))
        ->assertRedirect($url);

    $this->assertDatabaseHas('campaign_events', [
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'type' => 'click',
        'url' => $url,
    ]);
});
