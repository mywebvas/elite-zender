<?php

use App\Http\Controllers\AutomationController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\WebhookController;
use App\Http\Controllers\BounceController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactListController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataRequestController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SmtpAccountController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

// Marketing landing page. Signed-in users go straight to their workspace so
// the "logged in but staring at a signup CTA" dead end never happens.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('welcome');

// Offline fallback — served by the service worker when the network is down.
Route::view('/offline', 'offline')->name('offline');

/*
|--------------------------------------------------------------------------
| Email engagement endpoints (no session, hit by mail clients)
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:tracking')->group(function (): void {
    Route::get('/t/o/{campaign}/{contact}', [TrackingController::class, 'open'])->name('tracking.open');
    Route::get('/t/c/{campaign}/{contact}', [TrackingController::class, 'click'])->name('tracking.click');
});

// Signed so a link cannot be forged or replayed against another recipient.
// GET renders a confirmation page only; POST performs the opt-out (RFC 8058).
Route::match(['get', 'post'], '/unsubscribe/{campaign}/{contact}', UnsubscribeController::class)
    ->middleware(['throttle:tracking', 'signed'])
    ->name('unsubscribe');

/*
|--------------------------------------------------------------------------
| Invitations (public — the invitee has no account yet)
|--------------------------------------------------------------------------
| The token is the entire boundary: SHA-256 compared against a stored hash,
| single use, and expiring. Throttled because it is guessable in principle.
*/

Route::middleware('throttle:6,1')->group(function (): void {
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');
});

/*
|--------------------------------------------------------------------------
| Authenticated application
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:web'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/onboarding', OnboardingController::class)->name('onboarding');

    // Campaigns
    Route::post('campaigns/{campaign}/dispatch', [CampaignController::class, 'dispatch'])
        ->middleware('throttle:campaign-dispatch')
        ->name('campaigns.dispatch');
    Route::post('campaigns/{campaign}/retarget', [CampaignController::class, 'retarget'])->name('campaigns.retarget');
    Route::post('campaigns/{campaign}/test-send', [CampaignController::class, 'testSend'])
        ->middleware('throttle:campaign-dispatch')
        ->name('campaigns.test-send');
    Route::post('campaigns/preview', [CampaignController::class, 'preview'])->name('campaigns.preview');
    Route::resource('campaigns', CampaignController::class);

    // Automations
    Route::resource('automations', AutomationController::class);

    // SMTP pool
    Route::resource('smtp-accounts', SmtpAccountController::class)->except(['create', 'edit', 'show']);
    // A real handshake, throttled because it opens an outbound socket.
    Route::post('smtp-accounts/{smtp_account}/test', [SmtpAccountController::class, 'test'])
        ->middleware('throttle:10,1')
        ->name('smtp-accounts.test');

    // Audience
    Route::resource('lists', ContactListController::class)->except(['create', 'edit', 'show']);
    Route::resource('contacts', ContactController::class)->except(['create', 'edit', 'show']);

    Route::post('contacts/import', [CsvImportController::class, 'store'])
        ->middleware('throttle:csv-import')
        ->name('contacts.import');
    Route::get('contacts/import/{import}', [CsvImportController::class, 'show'])->name('contacts.import.status');

    Route::get('/bounces', BounceController::class)->name('bounces.index');

    /*
     | Billing. Viewing is open to the whole workspace; committing to spend is
     | gated to owners and admins inside the controllers.
     */
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::post('/billing/resume', [BillingController::class, 'resume'])->name('billing.resume');
    Route::post('/billing/keep-plan', [BillingController::class, 'keepPlan'])->name('billing.keep-plan');
    Route::delete('/billing/card', [BillingController::class, 'forgetCard'])->name('billing.card.forget');
    Route::get('/billing/invoices/{invoice}', [BillingController::class, 'showInvoice'])->name('billing.invoices.show');
    Route::post('/billing/invoices/{invoice}/checkout', [CheckoutController::class, 'start'])->name('billing.checkout.start');
    Route::get('/billing/invoices/{invoice}/callback', [CheckoutController::class, 'callback'])->name('billing.checkout.callback');
    Route::post('/billing/invoices/{invoice}/proof', [CheckoutController::class, 'uploadProof'])->name('billing.proof');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
    Route::put('/settings/imap', [SettingsController::class, 'updateImap'])->name('settings.imap');

    /*
     * Right of access and right to erasure (GDPR Art. 15 / 17), self-service.
     * Deletion is scheduled rather than immediate — a cooling-off window
     * turns an angry click into a recoverable decision.
     */
    Route::post('/settings/data/export', [DataRequestController::class, 'export'])
        ->middleware('throttle:3,60')
        ->name('data.export');
    Route::get('/settings/data/{dataRequest}/download', [DataRequestController::class, 'download'])->name('data.download');
    Route::post('/settings/data/delete', [DataRequestController::class, 'requestDeletion'])
        ->middleware('throttle:5,60')
        ->name('data.delete');
    Route::delete('/settings/data/{dataRequest}', [DataRequestController::class, 'cancelDeletion'])->name('data.delete.cancel');

    /*
     * Team seats. Every plan sells them; until now nothing could fill one.
     * Seat accounting counts pending invitations, so a three-seat workspace
     * cannot issue thirty that each pass the check individually.
     */
    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team/invitations', [TeamController::class, 'invite'])
        ->middleware('throttle:10,1')
        ->name('team.invite');
    Route::post('/team/invitations/{invitation}/resend', [TeamController::class, 'resend'])
        ->middleware('throttle:10,1')
        ->name('team.invitations.resend');
    Route::delete('/team/invitations/{invitation}', [TeamController::class, 'revoke'])->name('team.invitations.revoke');
    Route::put('/team/members/{user}/role', [TeamController::class, 'updateRole'])->name('team.members.role');
    Route::delete('/team/members/{user}', [TeamController::class, 'remove'])->name('team.members.remove');
});

/*
|--------------------------------------------------------------------------
| Payment webhooks
|--------------------------------------------------------------------------
| Unauthenticated by necessity and CSRF-exempt, so the provider's signature is
| the entire security boundary (see Billing\WebhookController).
*/

Route::post('/webhooks/billing/{gateway}', WebhookController::class)
    ->middleware('throttle:120,1')
    ->name('billing.webhook');

/*
|--------------------------------------------------------------------------
| PWA assets
|--------------------------------------------------------------------------
| In production nginx serves these straight from public/. The routes exist so
| the HTTP test client (and `php artisan serve`) resolve them identically.
*/

Route::get('/manifest.json', function () {
    $path = public_path('manifest.json');

    abort_unless(file_exists($path), 404);

    return response()->json(json_decode((string) file_get_contents($path), true));
})->name('manifest');

/*
 * Sitemap. Only the public marketing surface is listed — the authenticated app
 * is excluded here and in robots.txt.
 */
Route::get('/sitemap.xml', function () {
    $urls = [
        ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
        ['loc' => route('login'), 'priority' => '0.5', 'changefreq' => 'monthly'],
        ['loc' => route('register'), 'priority' => '0.8', 'changefreq' => 'monthly'],
    ];

    return response()
        ->view('sitemap', ['urls' => $urls])
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::get('/sw.js', function () {
    $path = public_path('sw.js');

    abort_unless(file_exists($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/javascript',
        'Service-Worker-Allowed' => '/',
    ]);
})->name('sw');
