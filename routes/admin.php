<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Operator console
|--------------------------------------------------------------------------
|
| Behind the `admin` guard, which has its own session cookie and its own user
| table. A customer session can never satisfy these routes, and an admin
| session is never visible to the tenant-facing application.
|
| Tenant scoping is deliberately absent: this is the one part of the system
| that is supposed to see across workspaces.
|
| Three privilege tiers:
|   admin            any active operator (read, impersonate)
|   admin:admin      may act on customer data
|   admin:super_admin may touch money, pricing, credentials and other operators
|
*/

Route::prefix('admin')->name('admin.')->group(function (): void {

    Route::middleware('guest:admin')->group(function (): void {
        Route::get('login', [Admin\AuthController::class, 'show'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'store'])->name('login.store');
    });

    Route::post('logout', [Admin\AuthController::class, 'destroy'])
        ->middleware('auth:admin')
        ->name('logout');

    Route::middleware('admin')->group(function (): void {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('search', Admin\SearchController::class)->name('search');

        Route::get('tenants', [Admin\TenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/{tenant}', [Admin\TenantController::class, 'show'])->name('tenants.show');

        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');

        Route::get('invoices', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('payments/{payment}/proof', [Admin\InvoiceController::class, 'proof'])->name('payments.proof');

        Route::get('activity', [Admin\ActivityController::class, 'index'])->name('activity.index');
        Route::get('activity/export', [Admin\ActivityController::class, 'export'])->name('activity.export');

        Route::get('system', [Admin\SystemController::class, 'index'])->name('system.index');

        Route::get('suppressions', [Admin\SuppressionController::class, 'index'])->name('suppressions.index');

        // Impersonation is open to every active operator, including read-only
        // support — reproducing a bug report needs it.
        Route::post('users/{user}/impersonate', [Admin\ImpersonationController::class, 'start'])->name('impersonate');

        /* ── Acting on customer data ─────────────────────────────────────── */
        Route::middleware('admin:admin')->group(function (): void {
            Route::put('tenants/{tenant}/status', [Admin\TenantController::class, 'updateStatus'])->name('tenants.status');
            Route::put('tenants/{tenant}/plan', [Admin\TenantController::class, 'changePlan'])->name('tenants.plan');

            Route::post('users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('users/{user}/sign-out', [Admin\UserController::class, 'signOut'])->name('users.sign-out');
            Route::put('users/{user}/role', [Admin\UserController::class, 'updateRole'])->name('users.role');

            Route::post('payments/{payment}/confirm', [Admin\InvoiceController::class, 'confirmTransfer'])->name('payments.confirm');
            Route::post('payments/{payment}/reject', [Admin\InvoiceController::class, 'rejectTransfer'])->name('payments.reject');
            Route::post('invoices/{invoice}/void', [Admin\InvoiceController::class, 'void'])->name('invoices.void');

            Route::post('suppressions', [Admin\SuppressionController::class, 'store'])->name('suppressions.store');
            Route::delete('suppressions/{suppression}', [Admin\SuppressionController::class, 'destroy'])->name('suppressions.destroy');

            Route::post('system/retry', [Admin\SystemController::class, 'retry'])->name('system.retry');
            Route::post('system/forget', [Admin\SystemController::class, 'forget'])->name('system.forget');
        });

        /* ── Money, pricing, credentials and other operators ─────────────── */
        Route::middleware('admin:super_admin')->group(function (): void {
            Route::get('plans', [Admin\PlanController::class, 'index'])->name('plans.index');
            Route::post('plans', [Admin\PlanController::class, 'store'])->name('plans.store');
            Route::put('plans/{plan}', [Admin\PlanController::class, 'update'])->name('plans.update');
            Route::delete('plans/{plan}', [Admin\PlanController::class, 'destroy'])->name('plans.destroy');

            Route::post('payments/{payment}/refund', [Admin\InvoiceController::class, 'refund'])->name('payments.refund');

            Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.index');
            Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
            Route::post('settings/reset', [Admin\SettingsController::class, 'reset'])->name('settings.reset');

            Route::get('api-keys', [Admin\ApiKeyController::class, 'index'])->name('api-keys.index');
            Route::post('api-keys', [Admin\ApiKeyController::class, 'store'])->name('api-keys.store');
            Route::delete('api-keys/{apiKey}', [Admin\ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

            Route::get('team', [Admin\AdminUserController::class, 'index'])->name('team.index');
            Route::post('team', [Admin\AdminUserController::class, 'store'])->name('team.store');
            Route::put('team/{admin}', [Admin\AdminUserController::class, 'update'])->name('team.update');
            Route::post('team/{admin}/toggle', [Admin\AdminUserController::class, 'toggle'])->name('team.toggle');
            Route::post('team/{admin}/reset-password', [Admin\AdminUserController::class, 'resetPassword'])->name('team.reset-password');
        });
    });
});

// Leaving impersonation happens from inside the customer UI, so it lives on
// the web guard rather than the admin one.
Route::post('stop-impersonating', [Admin\ImpersonationController::class, 'stop'])
    ->middleware('auth:web')
    ->name('impersonate.stop');
