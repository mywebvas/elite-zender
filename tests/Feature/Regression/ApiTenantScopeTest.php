<?php

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SmtpAccount;
use App\Models\User;
use App\Tenancy\TenantContext;
use Laravel\Sanctum\Sanctum;

/**
 * The public API had no tenant scope at all.
 *
 * `ResolveTenant` asked `auth('web')` for the acting user. A Bearer-token
 * request carries no session, so that returned null, no tenant was bound, and
 * the `HasTenant` global scope quietly became a no-op — every collection
 * endpoint under /api/v1 answered with *every workspace's* rows: contacts,
 * campaigns, lists, and the hostnames of other customers' SMTP relays.
 *
 * These tests exercise the real token path (no `actingAs` shortcut, which
 * would authenticate the `web` guard and hide the bug all over again).
 */
function tokenHeaders(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
}

beforeEach(function (): void {
    // Two populated workspaces. With one, a missing scope is invisible.
    $this->mine = User::factory()->create();
    $this->theirs = User::factory()->create();

    foreach ([$this->mine, $this->theirs] as $index => $owner) {
        TenantContext::run($owner->tenant, function () use ($index): void {
            Contact::factory()->count(3 + $index)->create();
            Campaign::factory()->count(2 + $index)->create();
            ContactList::factory()->count(1 + $index)->create();
            SmtpAccount::factory()->count(1 + $index)->create();
        });
    }

    // Nothing may be bound when the request arrives: the middleware is the
    // thing under test, not the test harness.
    TenantContext::set(null);
});

it('never lists another workspace through a bearer token', function (string $endpoint, string $model): void {
    $headers = tokenHeaders($this->mine);

    $response = $this->withHeaders($headers)->getJson("/api/v1/{$endpoint}?per_page=200");

    $response->assertOk();

    $mine = $model::withoutGlobalScopes()->where('tenant_id', $this->mine->tenant_id)->pluck('id');
    $theirs = $model::withoutGlobalScopes()->where('tenant_id', $this->theirs->tenant_id)->pluck('id');

    $returned = collect($response->json('data'))->pluck('id');

    expect($returned->sort()->values()->all())->toBe($mine->sort()->values()->all())
        ->and($returned->intersect($theirs)->all())->toBe([]);
})->with([
    'contacts' => ['contacts', Contact::class],
    'campaigns' => ['campaigns', Campaign::class],
    'lists' => ['lists', ContactList::class],
    'smtp relays' => ['smtp-accounts', SmtpAccount::class],
]);

it('404s a single record belonging to another workspace', function (): void {
    $headers = tokenHeaders($this->mine);

    $foreign = Contact::withoutGlobalScopes()->where('tenant_id', $this->theirs->tenant_id)->firstOrFail();

    $this->withHeaders($headers)->getJson("/api/v1/contacts/{$foreign->getKey()}")->assertNotFound();
    $this->withHeaders($headers)->deleteJson("/api/v1/contacts/{$foreign->getKey()}")->assertNotFound();

    expect(Contact::withoutGlobalScopes()->whereKey($foreign->getKey())->whereNull('deleted_at')->exists())->toBeTrue();
});

it('stamps a contact created over the API with the token owner tenant', function (): void {
    $headers = tokenHeaders($this->mine);

    $this->withHeaders($headers)
        ->postJson('/api/v1/contacts', ['email' => 'api-created@example.com'])
        ->assertCreated();

    $contact = Contact::withoutGlobalScopes()->firstWhere('email', 'api-created@example.com');

    expect($contact)->not->toBeNull()
        ->and($contact->tenant_id)->toBe($this->mine->tenant_id);
});

it('reports the token owner workspace on /me', function (): void {
    $this->withHeaders(tokenHeaders($this->mine))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.tenant.id', $this->mine->tenant_id);
});

it('refuses a token belonging to a suspended workspace', function (): void {
    $this->mine->tenant->forceFill(['status' => App\Models\Tenant::STATUS_SUSPENDED])->save();

    $this->withHeaders(tokenHeaders($this->mine))
        ->getJson('/api/v1/contacts')
        ->assertForbidden();
});

it('still scopes the SPA cookie path', function (): void {
    Sanctum::actingAs($this->mine, ['*']);

    $this->getJson('/api/v1/contacts?per_page=200')
        ->assertOk()
        ->assertJsonCount(
            Contact::withoutGlobalScopes()->where('tenant_id', $this->mine->tenant_id)->count(),
            'data',
        );
});
