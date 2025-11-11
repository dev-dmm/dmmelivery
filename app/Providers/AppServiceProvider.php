<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\Contracts\AnalyticsServiceInterface;
use App\Services\Contracts\AlertSystemServiceInterface;
use App\Services\Contracts\CacheServiceInterface;
use App\Services\Contracts\ChatbotServiceInterface;
use App\Services\Contracts\PredictiveEtaServiceInterface;
use App\Services\Contracts\WebSocketServiceInterface;
use App\Services\Contracts\SecurityServiceInterface;
use App\Services\Contracts\DMMDeliveryServiceInterface;
use App\Services\Contracts\CourierServiceInterface;
use App\Services\AnalyticsService;
use App\Services\AlertSystemService;
use App\Services\CacheService;
use App\Services\ChatbotService;
use App\Services\PredictiveEtaService;
use App\Services\WebSocketService;
use App\Services\SecurityService;
use App\Services\DMMDeliveryService;
use App\Services\ACSCourierService;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\Shipment;
use App\Observers\CustomerObserver;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register service interfaces with their implementations
        $this->app->singleton(AnalyticsServiceInterface::class, AnalyticsService::class);
        $this->app->singleton(AlertSystemServiceInterface::class, AlertSystemService::class);
        $this->app->singleton(CacheServiceInterface::class, CacheService::class);
        $this->app->singleton(ChatbotServiceInterface::class, ChatbotService::class);
        $this->app->singleton(PredictiveEtaServiceInterface::class, PredictiveEtaService::class);
        $this->app->singleton(WebSocketServiceInterface::class, WebSocketService::class);
        $this->app->singleton(SecurityServiceInterface::class, SecurityService::class);
        $this->app->singleton(DMMDeliveryServiceInterface::class, DMMDeliveryService::class);

        // Courier service is context-dependent (requires Courier model)
        // Register a factory closure that can be resolved with app()->make()
        // Usage: app()->makeWith(CourierServiceInterface::class, ['courier' => $courier])
        $this->app->bind(CourierServiceInterface::class, function ($app, $parameters) {
            $courier = $parameters['courier'] ?? null;
            if (!$courier instanceof Courier) {
                throw new \InvalidArgumentException('CourierServiceInterface requires a Courier instance. Use app()->makeWith(CourierServiceInterface::class, [\'courier\' => $courier])');
            }
            return new ACSCourierService($courier);
        });
        
        // Register a factory helper for easier instantiation
        $this->app->singleton('courier.service.factory', function ($app) {
            return function (Courier $courier) use ($app) {
                return $app->makeWith(CourierServiceInterface::class, ['courier' => $courier]);
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        
        // Configure tenant-aware API rate limiting
        // Each tenant gets its own rate limit bucket
        RateLimiter::for('api', function (Request $request) {
            $tenantId = tenant_id() ?? 'no-tenant';
            $userId = $request->user()?->getAuthIdentifier() ?? $request->ip();
            
            $key = sprintf('api:%s:%s', $tenantId, $userId);
            
            return Limit::perMinute(120)->by($key);
        });
        
        // Stricter rate limit for unauthenticated API requests
        RateLimiter::for('api:unauthenticated', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
        
        // WooCommerce-specific rate limiter (per tenant + IP)
        RateLimiter::for('woocommerce', function (Request $request) {
            $ip = $request->ip() ?? 'noip';
            $tenant = $request->header('X-Tenant-Id') 
                ?? $request->input('tenant_id') 
                ?? (function_exists('tenant_id') ? (tenant_id() ?? 'notenant') : 'notenant');
            
            // Normalize tenant ID to avoid bucket fragmentation (uppercase/trimmed variants)
            $tenant = strtolower(trim($tenant));
            
            $max = (int) config('rate.woocommerce_per_minute', 60);
            
            // Per (tenant+ip) rate limit
            return [
                Limit::perMinute($max)->by("woo:{$tenant}:{$ip}")
            ];
        });
        
        // Register observers
        Customer::observe(CustomerObserver::class);
        
        // Route model binding with tenant scoping for API routes
        // This ensures ShipmentPolicy is automatically applied via implicit binding
        Route::bind('shipment', function ($value) {
            $user = auth()->user();
            
            // For API routes, use policy-based authorization
            if ($user && $user->tenant_id) {
                return Shipment::query()
                    ->where('id', $value)
                    ->where('tenant_id', $user->tenant_id)
                    ->firstOrFail();
            }
            
            // Fallback for non-authenticated routes (should not happen for API)
            return Shipment::findOrFail($value);
        });
    }
}
