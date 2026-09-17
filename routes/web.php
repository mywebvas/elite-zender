<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root: redirect based on auth state
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Dashboard stub — will gain real content in M6 Analytics
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

// Offline fallback — served by service worker when network unavailable
Route::get('/offline', function () {
    return view('offline');
})->name('offline');

// Stub login/register routes (replaced by Fortify/auth scaffolding in M5)
Route::get('/login', function () {
    return response('Login page coming soon — M5 Auth UI', 200);
})->name('login');

Route::get('/register', function () {
    return response('Register page coming soon — M5 Auth UI', 200);
})->name('register');

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
