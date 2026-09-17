<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Base: /api/v1/  (see docs/05-API-CONTRACT.md)
| Auth: Sanctum cookie (SPA) + Bearer token (external clients)
| Rate limits registered in AppServiceProvider::boot()
|
*/

// ---------------------------------------------------------------------------
// Pre-auth: rate-limited per IP — no tenant context needed
// ---------------------------------------------------------------------------
Route::prefix('v1/auth')->middleware('throttle:login')->group(function () {
    // POST /api/v1/auth/register  — creates tenant + owner atomically
    Route::post('register', fn () => response()->json(['message' => 'Not implemented'], 501));

    // POST /api/v1/auth/login
    Route::post('login', fn () => response()->json(['message' => 'Not implemented'], 501));

    // POST /api/v1/auth/logout
    Route::post('logout', fn () => response()->json(['message' => 'Not implemented'], 501));

    // POST /api/v1/auth/forgot-password
    Route::post('forgot-password', fn () => response()->json(['message' => 'Not implemented'], 501));

    // POST /api/v1/auth/reset-password
    Route::post('reset-password', fn () => response()->json(['message' => 'Not implemented'], 501));
});

// ---------------------------------------------------------------------------
// Authenticated API — tenant-scoped, plan-tiered rate limit
// ---------------------------------------------------------------------------
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'throttle:api'])
    ->group(function () {
        // Health / ping
        Route::get('ping', fn (Request $request) => response()->json([
            'data' => [
                'user'   => $request->user()?->id,
                'tenant' => $request->user()?->tenant_id,
            ],
        ]));

        // Onboarding (M1 stub — returns 501 until implemented)
        Route::get('onboarding/status', fn () => response()->json(['message' => 'Not implemented'], 501));

        // Campaigns (M4 Campaigns module)
        Route::apiResource('campaigns', \App\Http\Controllers\Controller::class)
            ->only([])
            ->names('campaigns');

        // SMTP Accounts (M2 SMTP Pool module)
        // Route::apiResource('smtp-accounts', SmtpAccountController::class);

        // Contacts (M3 Contacts module)
        // Route::apiResource('contacts', ContactController::class);
    });
