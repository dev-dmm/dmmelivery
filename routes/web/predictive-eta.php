<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PredictiveEtaController;

/*
|--------------------------------------------------------------------------
| Predictive ETA Routes
|--------------------------------------------------------------------------
|
| Predictive ETA generation and management
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant', 'throttle:60,1'])
    ->prefix('predictive-eta')
    ->name('predictive-eta.')
    ->group(function () {
        Route::get('/', [PredictiveEtaController::class, 'index'])->name('index');
        Route::post('/generate/{shipment}', [PredictiveEtaController::class, 'generate'])->name('generate');
        Route::post('/update-all', [PredictiveEtaController::class, 'updateAll'])->name('update-all');
        Route::get('/{id}', [PredictiveEtaController::class, 'show'])->name('show');
    });

