<?php

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\SmtpAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * AuditLog — verifies append-only audit trail is written on key mutations.
 * Spec: docs/06-SECURITY-COMPLIANCE.md §1 Audit.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    TenantContext::set($this->tenant);
    $this->actingAs($this->user);
});

// ---------------------------------------------------------------------------
// User audit hooks
// ---------------------------------------------------------------------------

test('AuditLog is written when a user is created', function (): void {
    $initialCount = AuditLog::withoutGlobalScopes()->count();

    User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'New Member']);

    $log = AuditLog::withoutGlobalScopes()
        ->where('event', 'created')
        ->where('auditable_type', User::class)
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->new_values)->toHaveKey('name');
    // Password must never appear in audit log
    expect($log->new_values)->not->toHaveKey('password');
});

// ---------------------------------------------------------------------------
// SmtpAccount audit hooks
// ---------------------------------------------------------------------------

test('AuditLog is written when an SMTP account is created', function (): void {
    SmtpAccount::factory()->create(['name' => 'SendGrid Primary']);

    $log = AuditLog::withoutGlobalScopes()
        ->where('event', 'created')
        ->where('auditable_type', SmtpAccount::class)
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->new_values['name'])->toBe('SendGrid Primary');
});

test('AuditLog is written when an SMTP account is deleted', function (): void {
    $account = SmtpAccount::factory()->create();
    $account->delete();

    $log = AuditLog::withoutGlobalScopes()
        ->where('event', 'deleted')
        ->where('auditable_type', SmtpAccount::class)
        ->where('auditable_id', $account->id)
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull();
});

test('AuditLog old_values captures before state on update', function (): void {
    $account = SmtpAccount::factory()->create(['name' => 'Old Name']);
    $account->update(['name' => 'New Name']);

    $log = AuditLog::withoutGlobalScopes()
        ->where('event', 'updated')
        ->where('auditable_type', SmtpAccount::class)
        ->where('auditable_id', $account->id)
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->old_values['name'] ?? null)->toBe('Old Name');
    expect($log->new_values['name'] ?? null)->toBe('New Name');
});

// ---------------------------------------------------------------------------
// Campaign audit hooks
// ---------------------------------------------------------------------------

test('AuditLog is written when a campaign is created', function (): void {
    Campaign::factory()->create(['name' => 'Launch Blast']);

    $log = AuditLog::withoutGlobalScopes()
        ->where('event', 'created')
        ->where('auditable_type', Campaign::class)
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->new_values['name'])->toBe('Launch Blast');
});

test('AuditLog is written when a campaign is soft-deleted', function (): void {
    $campaign = Campaign::factory()->create();
    $campaign->delete();

    $log = AuditLog::withoutGlobalScopes()
        ->where('event', 'deleted')
        ->where('auditable_type', Campaign::class)
        ->where('auditable_id', $campaign->id)
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Immutability guard
// ---------------------------------------------------------------------------

test('AuditLog records cannot be updated', function (): void {
    $log = AuditLog::create([
        'event' => 'created',
        'auditable_type' => User::class,
        'auditable_id' => $this->user->id,
        'old_values' => [],
        'new_values' => ['name' => 'Test'],
    ]);

    expect(fn () => $log->update(['event' => 'tampered']))->toThrow(LogicException::class);
});

test('AuditLog records cannot be deleted', function (): void {
    $log = AuditLog::create([
        'event' => 'created',
        'auditable_type' => User::class,
        'auditable_id' => $this->user->id,
        'old_values' => [],
        'new_values' => [],
    ]);

    expect(fn () => $log->delete())->toThrow(LogicException::class);
});
