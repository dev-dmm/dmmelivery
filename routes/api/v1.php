<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ShipmentController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CourierController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\WebSocketController;
use App\Http\Controllers\Api\V1\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes v1
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for version 1 of your application.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint
Route::get('/health', [HealthController::class, 'check']);

// Public endpoints (no authentication required)
Route::prefix('public')->group(function () {
    Route::get('/shipments/{trackingNumber}/status', [ShipmentController::class, 'getPublicStatus']);
    Route::post('/webhooks/shipment-update', [ShipmentController::class, 'handleWebhook']);
});

// Authenticated endpoints
Route::middleware(['auth:sanctum', 'tenant.scope'])->group(function () {
    
    // Shipments
    Route::apiResource('shipments', ShipmentController::class);
    Route::get('shipments/{shipment}/tracking', [ShipmentController::class, 'getTrackingDetails']);
    Route::post('shipments/{shipment}/update-status', [ShipmentController::class, 'updateStatus']);
    Route::get('shipments/{shipment}/history', [ShipmentController::class, 'getStatusHistory']);
    
    // Orders
    Route::apiResource('orders', OrderController::class);
    Route::post('orders/{order}/create-shipment', [OrderController::class, 'createShipment']);
    Route::get('orders/{order}/shipments', [OrderController::class, 'getShipments']);
    
    // Customers
    Route::apiResource('customers', CustomerController::class);
    Route::get('customers/{customer}/shipments', [CustomerController::class, 'getShipments']);
    Route::get('customers/{customer}/orders', [CustomerController::class, 'getOrders']);
    
    // Couriers
    Route::apiResource('couriers', CourierController::class);
    Route::get('couriers/{courier}/shipments', [CourierController::class, 'getShipments']);
    Route::post('couriers/{courier}/test-connection', [CourierController::class, 'testConnection']);
    
    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('/performance', [AnalyticsController::class, 'performance']);
        Route::get('/trends', [AnalyticsController::class, 'trends']);
        Route::get('/predictions', [AnalyticsController::class, 'predictions']);
        Route::get('/alerts', [AnalyticsController::class, 'alerts']);
        Route::get('/geographic', [AnalyticsController::class, 'geographic']);
        Route::get('/customers', [AnalyticsController::class, 'customers']);
        Route::get('/couriers', [AnalyticsController::class, 'couriers']);
        Route::get('/summary', [AnalyticsController::class, 'summary']);
        Route::post('/export', [AnalyticsController::class, 'export']);
    });
    
    // WebSocket
    Route::prefix('websocket')->group(function () {
        Route::post('/authenticate', [WebSocketController::class, 'authenticate']);
        Route::get('/channels', [WebSocketController::class, 'getChannels']);
        Route::get('/test', [WebSocketController::class, 'test']);
    });
});

// Admin endpoints (super admin only)
Route::middleware(['auth:sanctum', 'super.admin'])->prefix('admin')->group(function () {
    Route::get('/tenants', [\App\Http\Controllers\Api\V1\AdminController::class, 'getTenants']);
    Route::get('/system-stats', [\App\Http\Controllers\Api\V1\AdminController::class, 'getSystemStats']);
    Route::post('/tenants/{tenant}/suspend', [\App\Http\Controllers\Api\V1\AdminController::class, 'suspendTenant']);
    Route::post('/tenants/{tenant}/activate', [\App\Http\Controllers\Api\V1\AdminController::class, 'activateTenant']);
});
