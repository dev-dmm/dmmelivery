<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Users Routes
|--------------------------------------------------------------------------
|
| User management routes
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant'])->group(function () {
    // Users
    Route::get('/users', function () {
        return Inertia::render('Users/Index');
    })->name('users.index');
});

