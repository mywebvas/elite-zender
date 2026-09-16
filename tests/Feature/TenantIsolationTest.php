<?php

use App\Models\SmtpAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Tenant isolation — the crown-jewel suite.
 * Every tenant-scoped model must be proven cross-tenant-safe.
 * See docs/06-SECURITY-COMPLIANCE.md §2 and docs/09-CODING-STANDARDS.md §7.
 */

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
    $this->userB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
});

test('HasTenant auto-assigns tenant_id on create', function () {
    TenantContext::set($this->tenantA);

    $account = SmtpAccount::factory()->create(['name' => 'Main']);

    expect($account->tenant_id)->toBe($this->tenantA->id);
});

test('global scope hides other tenants records', function () {
    TenantContext::set($this->tenantA);
    SmtpAccount::factory()->create(['name' => 'A-account']);

    TenantContext::set($this->tenantB);

    expect(SmtpAccount::count())->toBe(0);
});

test('cross-tenant access returns 404 (IDOR protection)', function () {
    TenantContext::set($this->tenantA);
    $account = SmtpAccount::factory()->create();

    TenantContext::set($this->tenantB);
    $this->actingAs($this->userB)
        ->getJson("/api/v1/smtp-accounts/{$account->id}")
        ->assertNotFound();
});

test('tenant scope is not bypassable via relations', function () {
    TenantContext::set($this->tenantA);
    $account = SmtpAccount::factory()->create();

    TenantContext::set($this->tenantB);
    $campaign = \App\Models\Campaign::factory()->create(['tenant_id' => $this->tenantB->id]);

    expect($campaign->smtpAccounts()->count())->toBe(0);
});
