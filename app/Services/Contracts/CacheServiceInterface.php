<?php

namespace App\Services\Contracts;

interface CacheServiceInterface
{
    /**
     * Cache courier API responses
     */
    public function cacheCourierResponse(string $trackingNumber, array $response, int $ttl = 300): void;

    /**
     * Get cached courier response
     */
    public function getCachedCourierResponse(string $trackingNumber): ?array;

    /**
     * Cache dashboard statistics
     */
    public function cacheDashboardStats(string $tenantId, string $period, array $stats, int $ttl = 600): void;

    /**
     * Get cached dashboard stats
     */
    public function getCachedDashboardStats(string $tenantId, string $period): ?array;

    /**
     * Clear cache for a specific tenant
     */
    public function clearTenantCache(string $tenantId): void;

    /**
     * Clear all cache
     */
    public function clearAllCache(): void;
}

