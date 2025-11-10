<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
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
    ->middleware(['throttle:woocommerce', 'woo.ratelimit.headers', 'woo.hmac']) // Named rate limiter + headers + HMAC verification
    ->group(function () {
        Route::match(['GET', 'HEAD'], '/ping', function (\Illuminate\Http\Request $request) {
            $ip     = $request->ip() ?? 'noip';
            $tenant = $request->header('X-Tenant-Id') ?? $request->input('tenant_id') ?? 'notenant';
            
            // Normalize tenant ID to match rate limiter key (avoid bucket fragmentation)
            $tenant = strtolower(trim($tenant));
            
            $bucket = "woo:{$tenant}:{$ip}";

            // Keep this in sync with AppServiceProvider 'woocommerce' limiter
            $limitPerMinute = (int) config('rate.woocommerce_per_minute', 60);

            // Remaining after this hit (throttle middleware has already "hit" the key)
            $remaining  = max(0, RateLimiter::remaining($bucket, $limitPerMinute));
            $retryAfter = RateLimiter::availableIn($bucket); // seconds until bucket resets (when empty)

            return response()->json([
                'success'       => true,
                'message'       => 'WooCommerce bridge OK',
                'tenant_id'     => $tenant,
                'ip'            => $ip,
                'limiter_key'   => $bucket,
                'hmac_verified' => (bool) $request->attributes->get('hmac_verified', false),
                'time'          => now()->toIso8601String(),
                'ratelimit'     => [
                    'limit'       => $limitPerMinute,
                    'remaining'   => $remaining,
                    'retry_after' => $retryAfter > 0 ? $retryAfter : null,
                    'reset'       => $retryAfter > 0 ? now()->addSeconds($retryAfter)->toIso8601String() : null,
                ],
            ]);
        })->name('ping');

        Route::post('/order', [WooCommerceOrderController::class, 'store'])
            ->name('order');
        Route::put('/order', [WooCommerceOrderController::class, 'update'])
            ->name('order.update');
        
        // ACS Shipment endpoint for WordPress plugin
        Route::post('/acs-shipment', [App\Http\Controllers\ACSShipmentController::class, 'store'])
            ->name('acs-shipment');
    });

// OPTIONS handler for CORS preflight (if not handled elsewhere)
Route::options('/{any}', fn () => response()->noContent())
    ->where('any', '.*');
