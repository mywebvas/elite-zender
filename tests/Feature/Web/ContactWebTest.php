<?php

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a user can view contacts', function () {
    $user = User::factory()->create();
    Contact::factory()->create([
        'tenant_id' => $user->tenant_id,
        'email' => 'john@example.com'
    ]);

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertSee('john@example.com');
});

test('a user can add a single contact', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('contacts.store'), [
            'email' => 'jane@example.com',
            'first_name' => 'Jane',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('contacts', [
        'tenant_id' => $user->tenant_id,
        'email' => 'jane@example.com',
        'first_name' => 'Jane'
    ]);
});

test('a user can import contacts via csv', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenant_id]);

    $csvContent = "Email,First Name,Last Name\nmark@example.com,Mark,Smith\nlucy@example.com,Lucy,Brown";
    $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent);

        $response = $this->actingAs($user)
        ->post(route('contacts.import'), [
            'csv_file' => $file,
            'list_id' => $list->id,
        ]);
        
        $response->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('contacts', [
        'email' => 'mark@example.com',
        'first_name' => 'Mark'
    ]);

    $this->assertDatabaseHas('contacts', [
        'email' => 'lucy@example.com',
        'last_name' => 'Brown'
    ]);

    // Check pivot
    $mark = Contact::where('email', 'mark@example.com')->first();
    $this->assertTrue($mark->lists->contains($list));
});
