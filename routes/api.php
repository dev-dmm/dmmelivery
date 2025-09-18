<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WooCommerceOrderController;

Route::prefix('woocommerce')
    ->name('api.woocommerce.')
    ->middleware('throttle:300,1') // Increased to 300 requests per minute for bulk processing
    ->group(function () {
        Route::post('/order', [WooCommerceOrderController::class, 'store'])
            ->name('order');
    });
