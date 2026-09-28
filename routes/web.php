<?php

use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BounceController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactListController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SmtpAccountController;
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
| Authenticated application
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/onboarding', OnboardingController::class)->name('onboarding');

    // Campaigns
    Route::post('campaigns/{campaign}/dispatch', [CampaignController::class, 'dispatch'])
        ->middleware('throttle:campaign-dispatch')
        ->name('campaigns.dispatch');
    Route::post('campaigns/{campaign}/retarget', [CampaignController::class, 'retarget'])->name('campaigns.retarget');
    Route::resource('campaigns', CampaignController::class);

    // Automations
    Route::resource('automations', AutomationController::class);

    // SMTP pool
    Route::resource('smtp-accounts', SmtpAccountController::class)->except(['create', 'edit', 'show']);

    // Audience
    Route::resource('lists', ContactListController::class)->except(['create', 'edit', 'show']);
    Route::resource('contacts', ContactController::class)->except(['create', 'edit', 'show']);

    Route::post('contacts/import', [CsvImportController::class, 'store'])
        ->middleware('throttle:csv-import')
        ->name('contacts.import');
    Route::get('contacts/import/{import}', [CsvImportController::class, 'show'])->name('contacts.import.status');

    Route::get('/bounces', BounceController::class)->name('bounces.index');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
});

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

Route::get('/sw.js', function () {
    $path = public_path('sw.js');

    abort_unless(file_exists($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/javascript',
        'Service-Worker-Allowed' => '/',
    ]);
})->name('sw');
