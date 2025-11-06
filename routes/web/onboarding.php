<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OnboardingController;

/*
|--------------------------------------------------------------------------
| Onboarding Routes
|--------------------------------------------------------------------------
|
| Tenant onboarding flow (must be authenticated, verified, and tenant-identified)
|
*/

Route::prefix('onboarding')
    ->name('onboarding.')
    ->middleware(['auth', 'verified', 'identify.tenant', 'throttle:60,1'])
    ->group(function () {
        Route::get('/welcome', [OnboardingController::class, 'welcome'])->name('welcome');

        Route::get('/profile', [OnboardingController::class, 'profile'])->name('profile');
        Route::post('/profile', [OnboardingController::class, 'updateProfile'])->name('onboarding.profile.update');

        Route::get('/branding', [OnboardingController::class, 'branding'])->name('branding');
        Route::post('/branding', [OnboardingController::class, 'updateBranding'])->name('branding.update');

        Route::get('/api-config', [OnboardingController::class, 'apiConfig'])->name('api-config');
        Route::post('/api-config', [OnboardingController::class, 'updateApiConfig'])->name('api-config.update');

        Route::get('/testing', [OnboardingController::class, 'testing'])->name('testing');
        Route::post('/test-api', [OnboardingController::class, 'testApi'])->name('test-api');

        Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
    });

