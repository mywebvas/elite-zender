<?php

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\LeadCaptureForm;
use App\Models\User;

/**
 * Regression: the capture endpoint accepted `tenant_id` and `list_id` from the
 * request body. Anyone who could read a rendered embed form (or simply guess a
 * UUID) could write contacts into another workspace.
 */
it('captures a lead through an opaque form key', function (): void {
    $owner = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $owner->tenant_id]);
    $form = LeadCaptureForm::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'list_id' => $list->id,
    ]);

    $this->postJson('/api/v1/leads/capture', [
        'form_key' => $form->public_key,
        'email' => 'Lead@Example.com',
        'first_name' => 'Lead',
    ])->assertCreated()->assertJsonPath('data.email', 'lead@example.com');

    $contact = Contact::withoutGlobalScopes()->where('email', 'lead@example.com')->sole();

    expect($contact->tenant_id)->toBe($owner->tenant_id)
        ->and($contact->lists->pluck('id')->all())->toBe([$list->id]);
});

it('cannot be pointed at another workspace', function (): void {
    $victim = User::factory()->create();

    // There is simply no field to name a tenant any more.
    $this->postJson('/api/v1/leads/capture', [
        'form_key' => 'pk_does_not_exist',
        'tenant_id' => $victim->tenant_id,
        'email' => 'intruder@example.com',
    ])->assertNotFound();

    expect(Contact::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects inactive forms', function (): void {
    $form = LeadCaptureForm::factory()->create(['is_active' => false]);

    $this->postJson('/api/v1/leads/capture', [
        'form_key' => $form->public_key,
        'email' => 'someone@example.com',
    ])->assertNotFound();
});

it('enforces the origin allow list when one is configured', function (): void {
    $form = LeadCaptureForm::factory()->create([
        'allowed_origins' => ['https://acme.test'],
    ]);

    $this->postJson('/api/v1/leads/capture', [
        'form_key' => $form->public_key,
        'email' => 'someone@example.com',
    ], ['Origin' => 'https://evil.test'])->assertForbidden();

    $this->postJson('/api/v1/leads/capture', [
        'form_key' => $form->public_key,
        'email' => 'someone@example.com',
    ], ['Origin' => 'https://acme.test'])->assertCreated();
});

it('traps bots with a honeypot field', function (): void {
    $form = LeadCaptureForm::factory()->create();

    $this->postJson('/api/v1/leads/capture', [
        'form_key' => $form->public_key,
        'email' => 'bot@example.com',
        'website' => 'http://spam.test',
    ])->assertStatus(422);
});

it('does not resurrect an unsubscribed contact', function (): void {
    $owner = User::factory()->create();
    $form = LeadCaptureForm::factory()->create(['tenant_id' => $owner->tenant_id]);

    $contact = Contact::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'email' => 'gone@example.com',
        'status' => Contact::STATUS_UNSUBSCRIBED,
    ]);

    $this->postJson('/api/v1/leads/capture', [
        'form_key' => $form->public_key,
        'email' => 'gone@example.com',
    ])->assertCreated();

    expect($contact->fresh()->status)->toBe(Contact::STATUS_UNSUBSCRIBED);
});
