<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WooCommerceOrderController;

Route::post('/woocommerce/order', [WooCommerceOrderController::class, 'store'])
    ->name('api.woocommerce.order')
    ->middleware('throttle:60,1'); // later you can swap/add Sanctum/HMAC/etc.
