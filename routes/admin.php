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
| Tenant scoping is deliberately absent here: this is the one part of the
| system that is supposed to see across workspaces.
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

        Route::get('tenants', [Admin\TenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/{tenant}', [Admin\TenantController::class, 'show'])->name('tenants.show');

        Route::get('invoices', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('payments/{payment}/proof', [Admin\InvoiceController::class, 'proof'])->name('payments.proof');

        // Impersonation is available to every active operator, including
        // read-only support — reproducing a bug report needs it.
        Route::post('users/{user}/impersonate', [Admin\ImpersonationController::class, 'start'])->name('impersonate');

        // Acting on customer data requires at least `admin`.
        Route::middleware('admin:admin')->group(function (): void {
            Route::put('tenants/{tenant}/status', [Admin\TenantController::class, 'updateStatus'])->name('tenants.status');
            Route::put('tenants/{tenant}/plan', [Admin\TenantController::class, 'changePlan'])->name('tenants.plan');

            Route::post('payments/{payment}/confirm', [Admin\InvoiceController::class, 'confirmTransfer'])->name('payments.confirm');
            Route::post('payments/{payment}/reject', [Admin\InvoiceController::class, 'rejectTransfer'])->name('payments.reject');
            Route::post('invoices/{invoice}/void', [Admin\InvoiceController::class, 'void'])->name('invoices.void');
        });

        // Pricing and refunds move real money — super admin only.
        Route::middleware('admin:super_admin')->group(function (): void {
            Route::get('plans', [Admin\PlanController::class, 'index'])->name('plans.index');
            Route::put('plans/{plan}', [Admin\PlanController::class, 'update'])->name('plans.update');
            Route::post('payments/{payment}/refund', [Admin\InvoiceController::class, 'refund'])->name('payments.refund');
        });
    });
});

// Leaving impersonation happens from inside the customer UI, so it lives on
// the web guard rather than the admin one.
Route::post('stop-impersonating', [Admin\ImpersonationController::class, 'stop'])
    ->middleware('auth:web')
    ->name('impersonate.stop');
