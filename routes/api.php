<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WooCommerceOrderController;
use App\Http\Controllers\Api\DocumentationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Documentation
Route::get('/docs', [DocumentationController::class, 'index']);

// Analytics API routes
Route::prefix('analytics')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\AnalyticsController::class, 'getOverview']);
    Route::get('/performance', [App\Http\Controllers\AnalyticsController::class, 'getPerformance']);
    Route::get('/trends', [App\Http\Controllers\AnalyticsController::class, 'getShipmentTrends']);
    Route::get('/predictions', [App\Http\Controllers\AnalyticsController::class, 'getPredictiveInsights']);
    Route::get('/geographic', [App\Http\Controllers\AnalyticsController::class, 'getGeographicDistribution']);
    Route::get('/customers', [App\Http\Controllers\AnalyticsController::class, 'getCustomerSegmentation']);
    Route::get('/couriers', [App\Http\Controllers\AnalyticsController::class, 'getCourierPerformance']);
});

// WebSocket authentication route
Route::post('/websocket/authenticate', [App\Http\Controllers\WebSocketController::class, 'authenticate']);

// API Versioning
Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/v1.php';
});

// Legacy WooCommerce endpoints
Route::prefix('woocommerce')
    ->name('api.woocommerce.')
    ->middleware('throttle:300,1') // Increased to 300 requests per minute for bulk processing
    ->group(function () {
        Route::post('/order', [WooCommerceOrderController::class, 'store'])
            ->name('order');
        Route::put('/order', [WooCommerceOrderController::class, 'update'])
            ->name('order.update');
    });
