<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WooCommerceOrderController;

Route::post('/woocommerce/order', [WooCommerceOrderController::class, 'store'])
    ->middleware('throttle:60,1'); // add Sanctum/HMAC later if you want
