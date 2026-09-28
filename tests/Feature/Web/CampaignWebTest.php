<?php

use App\Models\Campaign;
use App\Models\User;

it('can list campaigns', function (): void {
    $user = User::factory()->create();
    Campaign::factory(2)->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user)
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertViewIs('campaigns.index')
        ->assertViewHas('campaigns', function ($campaigns) {
            return $campaigns->count() === 2;
        });
});

it('can store a new campaign draft', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('campaigns.store'), [
            'name' => 'Black Friday',
            'subject' => '{Huge|Massive} Sale',
            'body_html' => '<p>Hello</p>',
        ])
        ->assertRedirect(route('campaigns.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('campaigns', [
        'tenant_id' => $user->tenant_id,
        'name' => 'Black Friday',
        'status' => 'draft',
    ]);
});

it('can delete a campaign', function (): void {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user)
        ->delete(route('campaigns.destroy', $campaign))
        ->assertRedirect(route('campaigns.index'));

    $this->assertSoftDeleted('campaigns', ['id' => $campaign->id]);
});
