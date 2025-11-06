<?php

namespace App\Services\Contracts;

interface AnalyticsServiceInterface
{
    /**
     * Get comprehensive analytics for a tenant
     */
    public function getTenantAnalytics(string $tenantId, array $filters = []): array;
}

