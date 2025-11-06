<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TenantRegistrationController;

/*
|--------------------------------------------------------------------------
| Registration & Email Verification Routes
|--------------------------------------------------------------------------
|
| Tenant registration and email verification (throttled + signed)
|
*/

Route::prefix('register')
    ->name('registration.')
    ->middleware('throttle:20,1')
    ->group(function () {
        Route::get('/', [TenantRegistrationController::class, 'showRegistrationForm'])->name('form');
        Route::post('/', [TenantRegistrationController::class, 'register'])->name('submit');

        Route::get('/email-verification', [TenantRegistrationController::class, 'showEmailVerification'])
            ->name('email-verification');

        // Signed URL + throttle to mitigate token abuse/leaks
        Route::get('/verify-email/{token}', [TenantRegistrationController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verify-email');

        Route::post('/check-subdomain', [TenantRegistrationController::class, 'checkSubdomain'])
            ->middleware('throttle:30,1')
            ->name('check-subdomain');
    });

