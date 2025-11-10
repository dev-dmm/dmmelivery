<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Profile Routes
|--------------------------------------------------------------------------
|
| User profile management (authenticated, no tenant required)
|
*/

Route::middleware(['auth'])->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // API token and secret management (rate limited for security)
    Route::post('/settings/api/generate', [SettingsController::class, 'generateApiToken'])
        ->middleware('throttle:6,1')
        ->name('settings.api.generate');
    Route::post('/settings/api/set-secret', [SettingsController::class, 'setApiSecret'])
        ->middleware('throttle:6,1')
        ->name('settings.api.set-secret');
});

