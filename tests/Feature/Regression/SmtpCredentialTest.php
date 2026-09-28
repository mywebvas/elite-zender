<?php

use App\Models\SmtpAccount;
use Illuminate\Support\Facades\Crypt;

/**
 * Regression: SmtpAccountController ran Crypt::encryptString() on a value that
 * the model already encrypts via the `encrypted` cast. The relay therefore
 * received ciphertext as its password and every SMTP handshake failed with an
 * authentication error that looked like a customer misconfiguration.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();
});

it('stores an SMTP password that decrypts back to the plaintext', function (): void {
    $this->post(route('smtp-accounts.store'), [
        'name' => 'Primary relay',
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'postmaster@example.com',
        'password' => 'sup3r-s3cret-passphrase',
        'from_email' => 'hello@example.com',
        'from_name' => 'Example',
        'encryption' => 'tls',
        'daily_limit' => 1000,
    ])->assertRedirect(route('smtp-accounts.index'));

    $account = SmtpAccount::where('name', 'Primary relay')->sole();

    expect($account->password)->toBe('sup3r-s3cret-passphrase');

    // And the column itself must not hold the plaintext.
    $raw = Illuminate\Support\Facades\DB::table('smtp_accounts')->where('id', $account->id)->value('password');
    expect($raw)->not->toBe('sup3r-s3cret-passphrase')
        ->and(Crypt::decryptString($raw))->toBe('sup3r-s3cret-passphrase');
});

it('keeps the existing password when the field is submitted empty', function (): void {
    $account = SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'password' => 'original-secret',
    ]);

    $this->put(route('smtp-accounts.update', $account), [
        'name' => 'Renamed',
        'host' => $account->host,
        'port' => $account->port,
        'password' => '',
        'from_email' => $account->from_email,
        'from_name' => $account->from_name,
    ])->assertRedirect(route('smtp-accounts.index'));

    expect($account->fresh()->password)->toBe('original-secret')
        ->and($account->fresh()->name)->toBe('Renamed');
});

it('never exposes the password through the API', function (): void {
    SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'password' => 'do-not-leak',
    ]);

    $this->getJson('/api/v1/smtp-accounts')
        ->assertOk()
        ->assertDontSee('do-not-leak')
        ->assertJsonMissingPath('data.0.password');
});
