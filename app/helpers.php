<?php

if (!function_exists('tenant')) {
    /**
     * Get the current tenant from the application container.
     * 
     * This is a convenience helper to avoid reaching into the container directly.
     * Returns null if no tenant is bound.
     * 
     * @return \App\Models\Tenant|null
     */
    function tenant(): ?\App\Models\Tenant
    {
        return app()->has('tenant') ? app('tenant') : null;
    }
}

if (!function_exists('tenant_id')) {
    /**
     * Get the current tenant ID from the application container.
     * 
     * Convenience helper for getting just the tenant ID without loading the full model.
     * Returns null if no tenant is bound.
     * 
     * @return string|null
     */
    function tenant_id(): ?string
    {
        return tenant()?->getKey();
    }
}

