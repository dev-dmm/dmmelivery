<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CourierReportImportController;

/*
|--------------------------------------------------------------------------
| Courier Reports Import Routes
|--------------------------------------------------------------------------
|
| Courier report import functionality (constrain params + throttle)
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant'])->group(function () {
    Route::prefix('courier-reports')->name('courier-reports.')->group(function () {
        Route::get('/import', [CourierReportImportController::class, 'index'])->name('import.index');
        Route::post('/import/upload', [CourierReportImportController::class, 'uploadFile'])->name('import.upload');

        Route::get('/import/{uuid}/status', [CourierReportImportController::class, 'getStatus'])
            ->whereUuid('uuid')->name('import.status');

        Route::get('/import/{uuid}/details', [CourierReportImportController::class, 'getDetails'])
            ->whereUuid('uuid')->name('import.details');

        Route::post('/import/{uuid}/cancel', [CourierReportImportController::class, 'cancel'])
            ->whereUuid('uuid')->name('import.cancel');

        Route::delete('/import/{uuid}', [CourierReportImportController::class, 'delete'])
            ->whereUuid('uuid')->name('import.delete');

        Route::get('/import/template/{format}', [CourierReportImportController::class, 'downloadTemplate'])
            ->whereIn('format', ['csv','xlsx','xls'])
            ->name('import.template');
    });
});

