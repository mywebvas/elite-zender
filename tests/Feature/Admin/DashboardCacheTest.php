<?php

use App\Models\Admin;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    seedPlans();
    $this->admin = Admin::factory()->create();
});

it('caches pure arrays and avoids incomplete object serialization issues', function (): void {
    Cache::forget('admin_dashboard_kpis_v2');

    // Simulate non-testing environment (e.g. local or production)
    App::detectEnvironment(fn () => 'local');

    // First request: primes the cache
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Subscriptions State');

    expect(Cache::has('admin_dashboard_kpis_v2'))->toBeTrue();

    // Verify cache payload is raw array, not serialized Collection
    $cached = Cache::get('admin_dashboard_kpis_v2');
    expect(is_array($cached))->toBeTrue()
        ->and(is_array($cached['subscriptions']))->toBeTrue()
        ->and(is_array($cached['planBreakdown']))->toBeTrue();

    // Second request: reads from cache and deserializes
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Subscriptions State')
        ->assertDontSee('incomplete object');

    // Reset environment back to testing
    App::detectEnvironment(fn () => 'testing');
});
