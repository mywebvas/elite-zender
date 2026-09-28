<?php

use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Role;
use App\Models\SmtpAccount;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Before this suite the application had no policies at all: every
 * authenticated member of a workspace could do everything, and Form Request
 * `authorize()` always returned true.
 */
it('forbids a viewer from creating a campaign', function (): void {
    actingAsTenantUser(['role' => Role::VIEWER]);

    $this->post(route('campaigns.store'), [
        'name' => 'Nope',
        'subject' => 'Nope',
        'body_html' => '<p>x</p>',
    ])->assertForbidden();

    expect(Campaign::count())->toBe(0);
});

it('lets a member create a campaign but not an SMTP relay', function (): void {
    actingAsTenantUser(['role' => Role::MEMBER]);

    $this->post(route('campaigns.store'), [
        'name' => 'Yes',
        'subject' => 'Yes',
        'body_html' => '<p>x</p>',
    ])->assertRedirect(route('campaigns.index'));

    // SMTP credentials can send mail as the customer's domain — admin only.
    $this->post(route('smtp-accounts.store'), [
        'name' => 'Relay',
        'host' => 'smtp.example.com',
        'port' => 587,
        'from_email' => 'a@example.com',
        'from_name' => 'A',
    ])->assertForbidden();
});

it('allows an admin to manage SMTP relays', function (): void {
    actingAsTenantUser(['role' => Role::ADMIN]);

    $this->post(route('smtp-accounts.store'), [
        'name' => 'Relay',
        'host' => 'smtp.example.com',
        'port' => 587,
        'from_email' => 'a@example.com',
        'from_name' => 'A',
    ])->assertRedirect(route('smtp-accounts.index'));
});

it('rejects a list id belonging to another workspace', function (): void {
    $victim = User::factory()->create();
    $foreignList = ContactList::factory()->create(['tenant_id' => $victim->tenant_id]);

    actingAsTenantUser();

    // A bare `exists:contact_lists,id` rule used to accept this.
    $this->post(route('contacts.store'), [
        'email' => 'intruder@example.com',
        'list_id' => $foreignList->id,
    ])->assertSessionHasErrors('list_id');

    expect($foreignList->contacts()->count())->toBe(0);
});

it('rejects an SMTP account id belonging to another workspace', function (): void {
    $victim = User::factory()->create();
    $foreignSmtp = SmtpAccount::factory()->create(['tenant_id' => $victim->tenant_id]);

    actingAsTenantUser();

    $this->post(route('campaigns.store'), [
        'name' => 'Cross tenant',
        'subject' => 'x',
        'body_html' => '<p>x</p>',
        'smtp_account_ids' => [$foreignSmtp->id],
    ])->assertSessionHasErrors('smtp_account_ids.0');
});

it('404s when reaching for another workspace campaign', function (): void {
    $victim = User::factory()->create();
    TenantContext::set($victim->tenant);
    $foreign = Campaign::factory()->create(['tenant_id' => $victim->tenant_id]);

    actingAsTenantUser();

    $this->get(route('campaigns.show', $foreign->id))->assertNotFound();
    $this->delete(route('campaigns.destroy', $foreign->id))->assertNotFound();

    expect(Campaign::withoutGlobalScopes()->whereKey($foreign->id)->whereNull('deleted_at')->exists())->toBeTrue();
});

it('only lets admins change workspace settings', function (): void {
    actingAsTenantUser(['role' => Role::MEMBER]);

    $this->put(route('settings.update'), ['workspace_name' => 'Hijacked'])->assertForbidden();

    $admin = actingAsTenantUser(['role' => Role::ADMIN]);

    $this->put(route('settings.update'), [
        'workspace_name' => 'Renamed workspace',
        'timezone' => 'Europe/Berlin',
    ])->assertRedirect();

    // Regression: the old handler validated the payload and discarded it.
    expect($admin->tenant->fresh()->name)->toBe('Renamed workspace')
        ->and($admin->tenant->fresh()->timezone())->toBe('Europe/Berlin');
});
