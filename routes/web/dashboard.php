<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\Dashboard\DashboardController;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
|
| Main dashboard, real-time dashboard, and courier performance views
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant'])->group(function () {
    // Main Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Real-time Dashboard
    Route::get('/realtime', [DashboardController::class, 'realtime'])->name('realtime.dashboard');
    
    // Performance view
    Route::get('/courier-performance', [DashboardController::class, 'courierPerformance'])
        ->name('courier.performance');
});

