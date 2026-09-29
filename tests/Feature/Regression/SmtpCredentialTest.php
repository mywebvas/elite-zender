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

/**
 * The send workers register a throwaway mailer per relay and remove it again,
 * because the config repository is shared for the life of an Octane worker.
 * The composer's "send a test to myself" path registered one and never
 * removed it, so one click left a customer's SMTP username and password in
 * the config of every subsequent request served by that worker.
 */
it('leaves no relay credentials in the config after a test send', function (): void {
    Illuminate\Support\Facades\Mail::fake();

    $campaign = App\Models\Campaign::factory()->create(['tenant_id' => $this->user->tenant_id]);

    SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'status' => SmtpAccount::STATUS_ACTIVE,
        'username' => 'postmaster@example.com',
        'password' => 'must-not-linger',
    ]);

    $this->post(route('campaigns.test-send', $campaign), ['test_email' => 'me@example.com'])
        ->assertRedirect();

    $leaked = collect(config('mail.mailers'))
        ->filter(fn ($mailer) => is_array($mailer) && ($mailer['password'] ?? null) === 'must-not-linger');

    expect($leaked)->toBeEmpty()
        ->and(array_keys(config('mail.mailers')))
        ->each->not->toStartWith('smtp_test_');
});
