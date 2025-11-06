<?php

namespace App\Services;

use App\Services\Contracts\CacheServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Shipment;
use App\Models\Order;
use App\Models\Tenant;
use Carbon\Carbon;

class CacheService implements CacheServiceInterface
{
    private const DEFAULT_TTL = 300; // 5 minutes
    private const LONG_TTL = 3600; // 1 hour
    private const DASHBOARD_TTL = 600; // 10 minutes

    /**
     * Cache courier API responses
     */
    public function cacheCourierResponse(string $trackingNumber, array $response, int $ttl = self::DEFAULT_TTL): void
    {
        $key = "courier_response:{$trackingNumber}";
        Cache::put($key, $response, $ttl);
        
        Log::info("Cached courier response", [
            'tracking_number' => $trackingNumber,
            'ttl' => $ttl,
            'response_keys' => array_keys($response)
        ]);
    }

    /**
     * Get cached courier response
     */
    public function getCachedCourierResponse(string $trackingNumber): ?array
    {
        $key = "courier_response:{$trackingNumber}";
        return Cache::get($key);
    }

    /**
     * Cache dashboard statistics
     */
    public function cacheDashboardStats(string $tenantId, string $period, array $stats, int $ttl = self::DASHBOARD_TTL): void
    {
        $key = "dashboard_stats:{$tenantId}:{$period}";
        Cache::put($key, $stats, $ttl);
        
        Log::info("Cached dashboard stats", [
            'tenant_id' => $tenantId,
            'period' => $period,
            'ttl' => $ttl
        ]);
    }

    /**
     * Get cached dashboard statistics
     */
    public function getCachedDashboardStats(string $tenantId, string $period): ?array
    {
        $key = "dashboard_stats:{$tenantId}:{$period}";
        return Cache::get($key);
    }

    /**
     * Cache shipment data with relationships
     */
    public function cacheShipmentData(string $shipmentId, array $data, int $ttl = self::DEFAULT_TTL): void
    {
        $key = "shipment_data:{$shipmentId}";
        Cache::put($key, $data, $ttl);
    }

    /**
     * Get cached shipment data
     */
    public function getCachedShipmentData(string $shipmentId): ?array
    {
        $key = "shipment_data:{$shipmentId}";
        return Cache::get($key);
    }

    /**
     * Cache recent shipments for tenant
     */
    public function cacheRecentShipments(string $tenantId, array $shipments, int $ttl = self::DEFAULT_TTL): void
    {
        $key = "recent_shipments:{$tenantId}";
        Cache::put($key, $shipments, $ttl);
    }

    /**
     * Get cached recent shipments
     */
    public function getCachedRecentShipments(string $tenantId): ?array
    {
        $key = "recent_shipments:{$tenantId}";
        return Cache::get($key);
    }

    /**
     * Cache courier performance data
     */
    public function cacheCourierStats(string $tenantId, array $stats, int $ttl = self::LONG_TTL): void
    {
        $key = "courier_stats:{$tenantId}";
        Cache::put($key, $stats, $ttl);
    }

    /**
     * Get cached courier stats
     */
    public function getCachedCourierStats(string $tenantId): ?array
    {
        $key = "courier_stats:{$tenantId}";
        return Cache::get($key);
    }

    /**
     * Cache predictive ETA data
     */
    public function cachePredictiveEta(string $shipmentId, array $data, int $ttl = self::LONG_TTL): void
    {
        $key = "predictive_eta:{$shipmentId}";
        Cache::put($key, $data, $ttl);
    }

    /**
     * Get cached predictive ETA
     */
    public function getCachedPredictiveEta(string $shipmentId): ?array
    {
        $key = "predictive_eta:{$shipmentId}";
        return Cache::get($key);
    }

    /**
     * Cache weather data
     */
    public function cacheWeatherData(string $city, array $data, int $ttl = 1800): void // 30 minutes
    {
        $key = "weather_data:{$city}";
        Cache::put($key, $data, $ttl);
    }

    /**
     * Get cached weather data
     */
    public function getCachedWeatherData(string $city): ?array
    {
        $key = "weather_data:{$city}";
        return Cache::get($key);
    }

    /**
     * Cache tenant configuration
     */
    public function cacheTenantConfig(string $tenantId, array $config, int $ttl = self::LONG_TTL): void
    {
        $key = "tenant_config:{$tenantId}";
        Cache::put($key, $config, $ttl);
    }

    /**
     * Get cached tenant configuration
     */
    public function getCachedTenantConfig(string $tenantId): ?array
    {
        $key = "tenant_config:{$tenantId}";
        return Cache::get($key);
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidateByPattern(string $pattern): void
    {
        // This would require Redis for pattern-based invalidation
        // For now, we'll use specific cache tags
        Log::info("Cache invalidation requested", ['pattern' => $pattern]);
    }

    /**
     * Invalidate shipment-related caches
     */
    public function invalidateShipmentCaches(string $shipmentId, string $tenantId): void
    {
        $keys = [
            "shipment_data:{$shipmentId}",
            "predictive_eta:{$shipmentId}",
            "recent_shipments:{$tenantId}",
            "dashboard_stats:{$tenantId}:*"
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Log::info("Invalidated shipment caches", [
            'shipment_id' => $shipmentId,
            'tenant_id' => $tenantId
        ]);
    }

    /**
     * Invalidate tenant caches
     */
    public function invalidateTenantCaches(string $tenantId): void
    {
        $keys = [
            "dashboard_stats:{$tenantId}:*",
            "recent_shipments:{$tenantId}",
            "courier_stats:{$tenantId}",
            "tenant_config:{$tenantId}"
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Log::info("Invalidated tenant caches", ['tenant_id' => $tenantId]);
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'driver' => config('cache.default'),
            'prefix' => config('cache.prefix'),
            'stores' => config('cache.stores')
        ];
    }

    /**
     * Clear all cache (use with caution)
     */
    public function clearAllCache(): void
    {
        Cache::flush();
        Log::warning("All caches cleared");
    }

    /**
     * Cache with automatic refresh
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Cache with tags (if supported by driver)
     */
    public function rememberWithTags(string $key, array $tags, int $ttl, callable $callback): mixed
    {
        if (method_exists(Cache::getStore(), 'tags')) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }
        
        return Cache::remember($key, $ttl, $callback);
    }
}
