<?php

use App\Billing\BillingService;
use App\Billing\PaymentGatewayManager;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Platform\Settings;
use App\Services\PricingCatalogue;

/**
 * What an operator edits must change everywhere a customer can see it.
 *
 * The marketing page hardcoded its own pricing — and not a stale copy of the
 * real catalogue, a different product entirely: a "$79 lifetime" tier and a
 * "$29/yr Pro" tier that existed nowhere, could not be bought, and
 * contradicted the schema.org offers a few lines above them on the same
 * page. A visitor clicking "Secure Lifetime Access — $79" landed on a
 * billing page selling monthly subscriptions at different prices.
 *
 * Meanwhile "Product name" in the console was written to the database and
 * read by nine hardcoded strings that ignored it.
 */
beforeEach(function (): void {
    seedPlans();
});

it('shows the catalogue price on the public homepage', function (): void {
    $growth = Plan::query()->where('code', 'growth')->sole();

    $this->get('/')
        ->assertOk()
        ->assertSee('$'.number_format($growth->price_usd / 100, 2))
        ->assertSee($growth->name);
});

it('moves the homepage price the moment an operator changes it', function (): void {
    Plan::query()->where('code', 'growth')->sole()->update(['price_usd' => 7700]);

    $this->get('/')
        ->assertOk()
        ->assertSee('$77.00')
        ->assertDontSee('$59.00');
});

it('generates the schema.org offers from the same rows', function (): void {
    Plan::query()->where('code', 'starter')->sole()->update(['price_usd' => 1900]);

    $offers = app(PricingCatalogue::class)->schemaOffers();

    $starter = collect($offers)->firstWhere('name', 'Starter');

    // A search result quoting a price the checkout will not honour is a
    // trust problem and, in several places, a legal one.
    expect($starter['price'])->toBe('19.00')
        ->and($starter['priceCurrency'])->toBe('USD');

    $this->get('/')->assertOk()->assertSee('"price":"19.00"', escape: false);
});

it('never advertises a plan that cannot be bought', function (): void {
    $response = $this->get('/');

    foreach (['$79', 'Lifetime Engine', 'Pro Engine', '$29', 'Refer & Earn'] as $ghost) {
        $response->assertDontSee($ghost);
    }
});

it('hides an archived plan from the public page immediately', function (): void {
    Plan::query()->where('code', 'scale')->sole()->update(['is_public' => false]);

    $this->get('/')->assertOk()->assertDontSee('$159.00');
});

it('renames the product everywhere when the operator renames it', function (): void {
    app(Settings::class)->put(['platform.name' => 'Acme Mail']);
    app(Settings::class)->apply();

    foreach (['/', '/terms', '/privacy', '/login', '/register'] as $url) {
        $this->get($url)->assertOk()->assertSee('Acme Mail');
    }
});

/*
|--------------------------------------------------------------------------
| Paystack: the currency nobody could set
|--------------------------------------------------------------------------
*/

it('bills a Nigerian workspace in naira once the country is set', function (): void {
    $user = actingAsTenantUser(['role' => Role::ADMIN]);

    // `settings.country` was read by currencyFor() and written by nothing —
    // there was no field for it anywhere — so every workspace on earth was
    // invoiced in USD, which a Nigerian Paystack account cannot charge.
    $this->put(route('settings.update'), [
        'workspace_name' => $user->tenant->name,
        'timezone' => 'Africa/Lagos',
        'country' => 'NG',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($user->tenant->fresh()->setting('country'))->toBe('NG')
        ->and(app(BillingService::class)->currencyFor($user->tenant->fresh()))->toBe('NGN');
});

it('raises the invoice in the workspace currency', function (): void {
    $user = actingAsTenantUser(['role' => Role::ADMIN]);
    $tenant = $user->tenant;
    $tenant->forceFill(['settings' => ['country' => 'NG']])->save();

    $invoice = app(BillingService::class)
        ->invoiceForPlan($tenant->fresh(), Plan::query()->where('code', 'growth')->sole());

    expect($invoice->currency)->toBe('NGN')
        ->and($invoice->total)->toBe(Plan::query()->where('code', 'growth')->sole()->price_ngn);
});

it('refuses to invoice in a currency no configured rail can collect', function (): void {
    // An NGN-only Paystack merchant with nothing else configured.
    config([
        'billing.gateways.paystack.secret_key' => 'sk_test',
        'billing.gateways.paystack.currencies' => ['NGN'],
        'billing.gateways.stripe.secret_key' => null,
        'billing.gateways.manual.enabled' => false,
        'billing.gateways.manual.currencies' => [],
        'billing.gateways.default' => 'paystack',
    ]);

    $tenant = Tenant::factory()->create(); // no country: prefers USD

    // USD has no rail here, so billing in it would produce an invoice the
    // customer is simply unable to pay.
    expect(app(BillingService::class)->preferredCurrencyFor($tenant))->toBe('USD')
        ->and(app(BillingService::class)->currencyFor($tenant))->toBe('NGN');
});

it('lets an operator narrow the currencies their merchant account accepts', function (): void {
    $settings = app(Settings::class);

    $settings->put(['billing.gateways.paystack.currencies' => 'ngn, NGN , usd']);
    $settings->apply();

    // Parsed, upper-cased and de-duplicated, and stored as a real array so
    // the in_array() checks that read it actually work.
    expect(config('billing.gateways.paystack.currencies'))->toBe(['NGN', 'USD'])
        ->and($settings->forDisplay()['billing.gateways.paystack.currencies'])->toBe('NGN, USD');

    $settings->put(['billing.gateways.paystack.currencies' => 'NGN']);
    $settings->apply();

    config(['billing.gateways.paystack.secret_key' => 'sk_test']);

    expect(app(PaymentGatewayManager::class)->get('paystack')->supports('USD'))->toBeFalse()
        ->and(app(PaymentGatewayManager::class)->availableFor('USD'))->not->toHaveKey('paystack');
});

/*
|--------------------------------------------------------------------------
| Settings form bugs found alongside
|--------------------------------------------------------------------------
*/

it('shows the workspace name, not the signed-in user name', function (): void {
    $user = actingAsTenantUser(['name' => 'Ada Lovelace', 'role' => Role::ADMIN]);
    $user->tenant->forceFill(['name' => 'Acme Corp'])->save();

    // The field was pre-filled with `auth()->user()->name`, so opening
    // settings and pressing save renamed the workspace to the person.
    $this->get(route('settings.index'))
        ->assertOk()
        ->assertSee('value="Acme Corp"', escape: false);
});

it('shows the timezone that is actually saved', function (): void {
    $user = actingAsTenantUser(['role' => Role::ADMIN]);
    $user->tenant->forceFill(['settings' => ['timezone' => 'Africa/Lagos']])->save();

    // Without @selected the dropdown always read UTC, and saving reset it.
    $this->get(route('settings.index'))
        ->assertOk()
        ->assertSee('value="Africa/Lagos" selected', escape: false);
});

/*
|--------------------------------------------------------------------------
| Legal pages that were href="#"
|--------------------------------------------------------------------------
*/

it('serves the terms and privacy pages the signup form links to', function (): void {
    $this->get(route('legal.terms'))->assertOk()->assertSee('Terms of Service');
    $this->get(route('legal.privacy'))->assertOk()->assertSee('Privacy Policy');

    // Terms nobody can read are terms nobody agreed to.
    $this->get(route('register'))
        ->assertOk()
        ->assertSee(route('legal.terms'), escape: false)
        ->assertSee(route('legal.privacy'), escape: false);
});

it('treats a blank currency list as "leave it alone", never as "erase it"', function (): void {
    // The settings form posts every field it renders, so a blank box must
    // not wipe the configured default and silently stop a gateway
    // supporting any currency at all.
    $settings = app(Settings::class);

    $settings->put(['billing.gateways.stripe.currencies' => '']);
    $settings->apply();

    expect(config('billing.gateways.stripe.currencies'))->toContain('USD');
});
