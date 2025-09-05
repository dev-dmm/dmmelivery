<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WooCommerceOrderController;

Route::prefix('woocommerce')
    ->name('api.woocommerce.')
    ->middleware('throttle:60,1')
    ->group(function () {
        Route::post('/order', [WooCommerceOrderController::class, 'store'])
            ->name('order');
    });
