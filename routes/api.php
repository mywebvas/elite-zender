<?php

use App\Http\Controllers\Api\LeadCaptureController;
use App\Http\Controllers\Api\V1;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Base: /api/v1/  (see docs/05-API-CONTRACT.md)
| Auth: Sanctum cookie (SPA) + Bearer token (external clients)
| Tenancy: App\Http\Middleware\ResolveTenant is applied to the api group in
|          bootstrap/app.php, so every query below is tenant-scoped.
| Limits:  rate limiters registered in AppServiceProvider::boot()
|
*/

Route::prefix('v1')->group(function (): void {
    /*
     * Public — authenticated by an opaque per-form public key, not by a
     * tenant id in the request body.
     */
    Route::post('leads/capture', [LeadCaptureController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('api.leads.capture');

    /*
     * Authenticated, tenant-scoped, plan-tiered rate limit.
     */
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('me', fn (Request $request) => response()->json([
            'data' => [
                'user' => [
                    'id' => $request->user()->getKey(),
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                ],
                'tenant' => [
                    'id' => $request->user()->tenant_id,
                    'name' => $request->user()->tenant?->name,
                ],
            ],
        ]))->name('api.me');

        // Lightweight connectivity probe for integrators.
        Route::get('ping', fn (Request $request) => response()->json([
            'data' => [
                'pong' => true,
                'user' => $request->user()?->getKey(),
                'tenant' => $request->user()?->tenant_id,
            ],
        ]))->name('api.ping');

        Route::apiResource('campaigns', V1\CampaignController::class)
            ->only(['index', 'show'])
            ->names('api.campaigns');

        Route::apiResource('contacts', V1\ContactController::class)
            ->only(['index', 'show', 'store', 'destroy'])
            ->names('api.contacts');

        Route::apiResource('lists', V1\ContactListController::class)
            ->only(['index', 'show'])
            ->names('api.lists');

        Route::apiResource('smtp-accounts', V1\SmtpAccountController::class)
            ->only(['index', 'show'])
            ->names('api.smtp-accounts');
    });
});
