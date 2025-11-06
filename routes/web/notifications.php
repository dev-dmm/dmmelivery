<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Notifications Routes
|--------------------------------------------------------------------------
|
| Notification management routes
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant'])->group(function () {
    // Notifications
    Route::get('/notifications', function () {
        return Inertia::render('Notifications/Index');
    })->name('notifications.index');
});

