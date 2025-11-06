<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShipmentController;

/*
|--------------------------------------------------------------------------
| Shipment Routes
|--------------------------------------------------------------------------
|
| Shipment management routes (IDOR protection via model binding + policy)
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant'])->group(function () {
    // Shipments
    Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('/shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
    Route::get('/shipments/search', [ShipmentController::class, 'search'])->name('shipments.search');

    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])
        ->middleware('can:view,shipment')  // ✅ Security maintained via policy
        ->name('shipments.show');
});

