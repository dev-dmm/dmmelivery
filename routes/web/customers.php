<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;

/*
|--------------------------------------------------------------------------
| Customer Routes
|--------------------------------------------------------------------------
|
| Routes for viewing customer profiles and statistics
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant'])->group(function () {
    // Customer profile page
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])
        ->name('customers.show');
});

