<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AlertController;

/*
|--------------------------------------------------------------------------
| Alert System Routes
|--------------------------------------------------------------------------
|
| Alert management and rule configuration
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant', 'throttle:60,1'])
    ->prefix('alerts')
    ->name('alerts.')
    ->group(function () {
        Route::get('/', [AlertController::class, 'index'])->name('index');
        Route::get('/rules', [AlertController::class, 'rules'])->name('rules');
        Route::post('/rules', [AlertController::class, 'createRule'])->name('rules.create');
        Route::put('/rules/{id}', [AlertController::class, 'updateRule'])->name('rules.update');
        Route::delete('/rules/{id}', [AlertController::class, 'deleteRule'])->name('rules.delete');
        Route::post('/{id}/acknowledge', [AlertController::class, 'acknowledge'])->name('acknowledge');
        Route::post('/{id}/resolve', [AlertController::class, 'resolve'])->name('resolve');
        Route::post('/{id}/escalate', [AlertController::class, 'escalate'])->name('escalate');
        Route::post('/check', [AlertController::class, 'checkAlerts'])->name('check');
        Route::get('/{id}', [AlertController::class, 'show'])->name('show');
    });

