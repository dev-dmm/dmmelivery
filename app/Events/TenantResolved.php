<?php

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * TenantResolved Event
 * 
 * Fired when a tenant is successfully resolved and bound to the application.
 * Listeners can use this to:
 * - Switch database connections (multi-tenant DB per tenant)
 * - Switch database schemas (schema per tenant)
 * - Configure URL roots for subdomain-based tenants
 * - Set feature flags per tenant
 * - Initialize tenant-specific services
 */
class TenantResolved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant
    ) {
    }
}

