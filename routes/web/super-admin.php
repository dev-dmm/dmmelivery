<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\UserManagementController;

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
|
| Super admin dashboard and tenant/user management
|
*/

Route::prefix('super-admin')
    ->name('super-admin.')
    ->middleware(['auth', 'verified', 'super.admin', 'throttle:60,1'])
    ->group(function () {
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/orders', [SuperAdminController::class, 'orders'])->name('orders');
        Route::get('/order-items', [SuperAdminController::class, 'orderItems'])->name('order-items');
        Route::get('/tenants', [SuperAdminController::class, 'tenants'])->name('tenants');
        Route::get('/tenants/{tenant}', [SuperAdminController::class, 'tenantDetails'])->name('tenants.show');
        
        // Courier Management
        Route::get('/tenants/{tenant}/couriers', [SuperAdminController::class, 'tenantCouriers'])->name('tenants.couriers');
        Route::post('/tenants/{tenant}/couriers', [SuperAdminController::class, 'createCourier'])->name('tenants.couriers.create');
        Route::post('/tenants/{tenant}/couriers/acs', [SuperAdminController::class, 'createACSCourier'])->name('tenants.couriers.create-acs');
        Route::put('/tenants/{tenant}/couriers/{courier}', [SuperAdminController::class, 'updateCourier'])->name('tenants.couriers.update');
        Route::delete('/tenants/{tenant}/couriers/{courier}', [SuperAdminController::class, 'deleteCourier'])->name('tenants.couriers.delete');
        
        // User Management
        Route::get('/users', [UserManagementController::class, 'index'])->name('users');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.update-role');
        Route::patch('/users/{user}/toggle-active', [UserManagementController::class, 'toggleActive'])->name('users.toggle-active');
    });

