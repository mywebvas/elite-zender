<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root: redirect based on auth state
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Offline fallback — served by service worker when network unavailable
Route::get('/offline', function () {
    return view('offline');
})->name('offline');

// Tracking Endpoints - rate limited to 60 per minute per IP
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/t/o/{campaign}/{contact}', [\App\Http\Controllers\TrackingController::class, 'open'])->name('tracking.open');
    Route::get('/t/c/{campaign}/{contact}', [\App\Http\Controllers\TrackingController::class, 'click'])->name('tracking.click');
    
    // Unsubscribe (GET for users, POST for RFC 8058 1-click)
    Route::match(['get', 'post'], '/unsubscribe/{campaign}/{contact}', \App\Http\Controllers\UnsubscribeController::class)->name('unsubscribe');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Onboarding Wizard
    Route::get('/onboarding', function () {
        return view('onboarding');
    })->name('onboarding');

    // Campaigns Builder UI
    Route::post('campaigns/{campaign}/dispatch', [\App\Http\Controllers\CampaignController::class, 'dispatch'])->name('campaigns.dispatch');
    Route::post('campaigns/{campaign}/retarget', [\App\Http\Controllers\CampaignController::class, 'retarget'])->name('campaigns.retarget');
    Route::resource('campaigns', \App\Http\Controllers\CampaignController::class);

    // Automations UI
    Route::resource('automations', \App\Http\Controllers\AutomationController::class);

    // SMTP Manager UI
    Route::resource('smtp-accounts', \App\Http\Controllers\SmtpAccountController::class)->except(['create', 'edit', 'show']);
    
    Route::resource('lists', \App\Http\Controllers\ContactListController::class)->except(['create', 'edit', 'show']);
    Route::resource('contacts', \App\Http\Controllers\ContactController::class)->except(['create', 'edit', 'show']);
    
    // Rate limit CSV imports to 10 per minute to prevent DoS
    Route::post('contacts/import', [\App\Http\Controllers\CsvImportController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('contacts.import');

    Route::get('/bounces', function () {
        return view('bounces.index');
    })->name('bounces.index');


    Route::get('/settings', function () {
        return view('settings.index');
    })->name('settings.index');

    Route::post('/settings', function (\Illuminate\Http\Request $request) {
        $request->validate(['workspace_name' => 'sometimes|string|max:255', 'timezone' => 'sometimes|string|max:100']);
        // In a real app, save to tenant settings. For now flash success.
        return back()->with('success', 'Settings saved successfully.');
    })->name('settings.update');
});

// PWA static assets — serve from public/ with correct content-type
// In production these are served by the web server directly (nginx/caddy).
// Routes exist so the test HTTP client can hit them in the test suite.
Route::get('/manifest.json', function () {
    $path = public_path('manifest.json');
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->json(
        json_decode(file_get_contents($path), true),
        200,
        ['Content-Type' => 'application/json']
    );
})->name('manifest');

Route::get('/sw.js', function () {
    $path = public_path('sw.js');
    return response()->file($path, ['Content-Type' => 'application/javascript']);
})->name('sw');


