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

// Fortify handles auth routes. If API tokens are used later, they use Sanctum's createToken.

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
