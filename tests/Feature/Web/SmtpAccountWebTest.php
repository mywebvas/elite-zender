<?php

use App\Models\SmtpAccount;
use App\Models\User;

it('can list smtp accounts', function (): void {
    $user = User::factory()->create();
    SmtpAccount::factory(3)->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user)
        ->get(route('smtp-accounts.index'))
        ->assertOk()
        ->assertViewIs('smtp.index')
        ->assertViewHas('accounts', function ($accounts) {
            return $accounts->count() === 3;
        });
});

it('can create an smtp account', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('smtp-accounts.store'), [
            'name' => 'Mailgun',
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'username' => 'postmaster@mg.org',
            'password' => 'secret',
            'from_email' => 'sales@acme.org',
            'from_name' => 'Acme Sales',
            'encryption' => 'tls',
            'daily_limit' => 1000,
        ])
        ->assertRedirect(route('smtp-accounts.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('smtp_accounts', [
        'tenant_id' => $user->tenant_id,
        'name' => 'Mailgun',
        'host' => 'smtp.mailgun.org',
        'port' => 587,
    ]);
});

it('can delete an smtp account', function (): void {
    $user = User::factory()->create();
    $account = SmtpAccount::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user)
        ->delete(route('smtp-accounts.destroy', $account))
        ->assertRedirect(route('smtp-accounts.index'));

    $this->assertSoftDeleted('smtp_accounts', ['id' => $account->id]);
});
