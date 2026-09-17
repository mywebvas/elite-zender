<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root: redirect based on auth state
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Offline fallback — served by service worker when network unavailable
Route::get('/offline', function () {
    return view('offline');
})->name('offline');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Onboarding Wizard
    Route::get('/onboarding', function () {
        return view('onboarding');
    })->name('onboarding');

    // Campaigns Builder UI
    Route::get('/campaigns', function () {
        return view('campaigns.index');
    })->name('campaigns.index');
    Route::get('/campaigns/create', function () {
        return view('campaigns.builder');
    })->name('campaigns.create');

    // SMTP Manager UI
    Route::get('/smtp-accounts', function () {
        return view('smtp.index');
    })->name('smtp.index');
    
    // Contacts UI (basic stub for onboarding)
    Route::get('/contacts', function () {
        return view('contacts.index');
    })->name('contacts.index');
});

// PWA static assets — serve from public/ with correct content-type
// In production these are served by the web server directly (nginx/caddy).
// Routes exist so the test HTTP client can hit them in the test suite.
Route::get('/manifest.json', function () {
    return response()->json(
        json_decode(file_get_contents(public_path('manifest.json')), true),
        200,
        ['Content-Type' => 'application/json']
    );
})->name('manifest');

Route::get('/sw.js', function () {
    $path = public_path('sw.js');
    return response()->file($path, ['Content-Type' => 'application/javascript']);
})->name('sw');
