<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;

/*
|--------------------------------------------------------------------------
| Analytics Routes
|--------------------------------------------------------------------------
|
| Analytics dashboard and export functionality
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant'])->group(function () {
    // Analytics Dashboard
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/advanced', [AnalyticsController::class, 'advanced'])->name('analytics.advanced');
    Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
});

