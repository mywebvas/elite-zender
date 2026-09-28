<?php

use App\Models\ContactList;
use App\Models\User;

test('a user can view their lists', function () {
    $user = User::factory()->create();
    
    ContactList::factory()->create(['tenant_id' => $user->tenant_id, 'name' => 'My List']);

    $this->actingAs($user)
        ->get(route('lists.index'))
        ->assertOk()
        ->assertSee('My List');
});

test('a user can create a list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('lists.store'), [
            'name' => 'New Newsletter',
        ])
        ->assertRedirect(route('lists.index'));

    $this->assertDatabaseHas('contact_lists', [
        'tenant_id' => $user->tenant_id,
        'name' => 'New Newsletter',
    ]);
});

test('a user can delete a list', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user)
        ->delete(route('lists.destroy', $list))
        ->assertRedirect(route('lists.index'));

    $this->assertSoftDeleted('contact_lists', [
        'id' => $list->id,
    ]);
});
