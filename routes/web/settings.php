<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Settings Routes
|--------------------------------------------------------------------------
|
| Application settings management (keep sensitive actions POST/DELETE and CSRF-protected)
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant'])->group(function () {
    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/business', [SettingsController::class, 'updateBusiness'])->name('settings.business.update');
    Route::post('/settings/courier/acs', [SettingsController::class, 'updateACSCredentials'])->name('settings.courier.acs.update');
    Route::post('/settings/courier/test', [SettingsController::class, 'testCourierConnection'])->name('settings.courier.test');
    Route::post('/settings/courier/update', [SettingsController::class, 'updateCourierCredentials'])->name('settings.courier.update');
    Route::delete('/settings/courier/delete', [SettingsController::class, 'deleteCourierCredentials'])->name('settings.courier.delete');
    Route::post('/settings/webhooks', [SettingsController::class, 'updateWebhooks'])->name('settings.webhooks.update');
    Route::post('/settings/download/plugin', [SettingsController::class, 'downloadPlugin'])->name('settings.download.plugin');
    Route::get('/settings/download/plugin/{filename}', [SettingsController::class, 'downloadPluginFile'])->name('settings.download.plugin.file');
});

