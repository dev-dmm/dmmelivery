<?php

namespace App\Http\Middleware\Concerns;

use Illuminate\Http\Request;
use App\Models\Tenant;

trait IdentifiesTenant
{
    /**
     * Resolve tenant from request using priority order:
     * 1. Super-admin override (header/query) - HTTPS only in production
     * 2. Route parameter {tenant}
     * 3. Subdomain
     * 4. Authenticated user's tenant
     * 
     * Results are memoized per request to avoid repeated queries.
     *
     * @param Request $request
     * @return Tenant|null
     */
    protected function resolveTenantFromRequest(Request $request): ?Tenant
    {
        // Memoize resolution per request
        if ($request->attributes->has('__resolved_tenant')) {
            return $request->attributes->get('__resolved_tenant');
        }

        $tenant = $this->doResolveTenant($request);
        $request->attributes->set('__resolved_tenant', $tenant);
        
        return $tenant;
    }

    /**
     * Perform the actual tenant resolution logic.
     * 
     * @param Request $request
     * @return Tenant|null
     */
    protected function doResolveTenant(Request $request): ?Tenant
    {
        // 1) Super-admin override (e.g. header or query) – guard tightly
        // Check both default guard and sanctum guard for flexibility
        $user = $request->user() ?? $request->user('sanctum');
        if ($user && $user->isSuperAdmin()) {
            $id = $request->header('X-Tenant-ID') ?? $request->query('tenant_id');
            
            // Only accept UUIDs (or your ID format) and require HTTPS in production
            if ($id && $this->isValidTenantId($id) && $this->isSecureRequest($request)) {
                $tenant = Tenant::query()->active()->whereKey($id)->first();
                if ($tenant) {
                    // Audit log the override
                    $this->auditTenantOverride($request, $tenant, $user);
                    return $tenant;
                }
            }
        }

        // 2) Route parameter {tenant}
        // Note: In Laravel 11, route group middleware (SubstituteBindings) runs before
        // route middleware. If tenant middleware is route-level, {tenant} will be a string,
        // not a bound model. If you want a bound Tenant instance, move middleware to group
        // before SubstituteBindings.
        if ($param = $request->route('tenant')) {
            if ($param instanceof Tenant) {
                return $param;
            }
            
            // Try UUID first (cheap regex check)
            if ($this->isValidTenantId($param)) {
                $tenant = $this->getCachedTenant('id', $param);
                if ($tenant) {
                    return $tenant;
                }
            } else {
                // Try by subdomain/slug if not a UUID
                $tenant = $this->getCachedTenant('subdomain', $param);
                if ($tenant) {
                    return $tenant;
                }
            }
        }

        // 3) Domain-based resolution (primary domain first, then subdomain)
        // Note: Ensure TrustedProxy is configured if behind a load balancer
        // so getHost() returns the correct forwarded host
        if ($host = $request->getHost()) {
            // 3a) Exact primary/custom domain (including www)
            $tenant = $this->getCachedTenant('primary_domain', $host);
            if ($tenant) {
                return $tenant;
            }
            
            // Try without www prefix
            if (str_starts_with($host, 'www.')) {
                $apex = substr($host, 4);
                $tenant = $this->getCachedTenant('primary_domain', $apex);
                if ($tenant) {
                    return $tenant;
                }
            }
            
            // 3b) Subdomain (acme.yourapp.com)
            $parts = explode('.', $host);
            $sub = $parts[0] ?? null;
            
            if ($sub && !$this->isReservedSubdomain($sub)) {
                $tenant = $this->getCachedTenant('subdomain', $sub);
                if ($tenant) {
                    return $tenant;
                }
            }
        }

        // 4) Authenticated user's tenant
        return $this->getTenantForUser($request);
    }

    /**
     * Validate tenant ID format (UUID or other format).
     * 
     * @param string $id
     * @return bool
     */
    protected function isValidTenantId(string $id): bool
    {
        // Accept UUIDs (36 chars with hyphens)
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1;
    }

    /**
     * Check if request is secure (HTTPS).
     * In production, super-admin overrides should only work over HTTPS.
     * 
     * @param Request $request
     * @return bool
     */
    protected function isSecureRequest(Request $request): bool
    {
        // In production, require HTTPS; in local/testing, allow HTTP
        if (app()->environment('production')) {
            return $request->isSecure();
        }
        
        return true; // Allow in development
    }

    /**
     * Audit log tenant override by super-admin.
     * 
     * @param Request $request
     * @param Tenant $tenant
     * @param \App\Models\User|null $admin
     * @return void
     */
    protected function auditTenantOverride(Request $request, Tenant $tenant, $admin = null): void
    {
        $admin = $admin ?? $request->user() ?? $request->user('sanctum');
        
        \Log::info('Tenant override by super-admin', [
            'admin_user_id' => $admin->id ?? null,
            'admin_email' => $admin->email ?? null,
            'target_tenant_id' => $tenant->getKey(),
            'target_tenant_name' => $tenant->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'override_source' => $request->header('X-Tenant-ID') ? 'header' : 'query',
        ]);
        
        // Fire event for notifications/alerts
        event(new \App\Events\TenantOverrideUsed($admin, $tenant, $request));
    }

    /**
     * Get the tenant for the authenticated user.
     * Checks both default guard and sanctum guard for flexibility.
     *
     * @param Request $request
     * @return Tenant|null
     */
    protected function getTenantForUser(Request $request): ?Tenant
    {
        // Check both guards to support web and API routes
        $user = $request->user() ?? $request->user('sanctum');
        
        if (!$user) {
            return null;
        }

        return $user->tenant ?? null;
    }

    /**
     * Bind tenant to the application container and request.
     * Fires TenantResolved event for listeners (DB switching, URL roots, etc.).
     *
     * @param Request $request
     * @param Tenant|null $tenant
     * @return void
     */
    protected function bindTenant(Request $request, ?Tenant $tenant): void
    {
        if ($tenant) {
            app()->instance('tenant', $tenant);
            $request->attributes->set('tenant', $tenant);
            
            // Add tenant context to logs
            \Log::withContext(['tenant_id' => $tenant->getKey()]);
            
            // Fire event for listeners (DB switching, feature flags, etc.)
            event(new \App\Events\TenantResolved($tenant));
        } else {
            $this->clearTenant();
        }
    }

    /**
     * Clear tenant from the application container.
     * Fires TenantCleared event for cleanup.
     *
     * @return void
     */
    protected function clearTenant(): void
    {
        if (app()->has('tenant')) {
            $tenant = app('tenant');
            app()->forgetInstance('tenant');
            
            // Remove only tenant_id from context (preserve other context)
            \Log::withContext(['tenant_id' => null]);
            
            // Fire event for cleanup
            event(new \App\Events\TenantCleared($tenant ?? null));
        }
    }

    /**
     * Check if tenant is valid and active.
     * Validates that tenant exists, is active, and not suspended/deleted.
     *
     * @param Tenant|null $tenant
     * @return bool
     */
    protected function isValidTenant(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        if (!$tenant->is_active) {
            return false;
        }

        // Check for soft deletes if using SoftDeletes trait
        if (method_exists($tenant, 'trashed') && $tenant->trashed()) {
            return false;
        }

        // Check for suspended_at if the field exists
        if (isset($tenant->suspended_at) && $tenant->suspended_at !== null) {
            return false;
        }

        return true;
    }

    /**
     * Get payload for invalid tenant responses.
     * Side-effect free - returns data only, no session/auth mutations.
     * Includes machine-friendly error code for API consumers.
     *
     * @return array
     */
    protected function invalidTenantPayload(): array
    {
        return [
            'error' => 'InvalidTenant',
            'code' => 'TENANT_NOT_FOUND_OR_INACTIVE',
            'message' => 'Tenant not found or inactive.'
        ];
    }

    /**
     * Get cached tenant by field and value.
     * Falls back to database query if not cached.
     * Gracefully handles cache drivers that don't support tagging.
     * 
     * @param string $field
     * @param string $value
     * @return Tenant|null
     */
    protected function getCachedTenant(string $field, string $value): ?Tenant
    {
        $ttl = config('tenancy.cache_ttl');
        
        if ($ttl === null) {
            // Caching disabled
            return $this->queryTenantByField($field, $value);
        }
        
        $cacheKey = "tenant:{$field}:{$value}";
        
        // Try to use cache tags if supported (only Redis and Memcached support tags)
        // Use try-catch as a safe fallback in case the method doesn't exist or throws
        try {
            $cache = \Cache::store();
            if (method_exists($cache, 'supportsTags') && $cache->supportsTags()) {
                $tags = config('tenancy.cache_tags', ['tenants']);
                return \Cache::tags($tags)->remember($cacheKey, $ttl, function () use ($field, $value) {
                    return $this->queryTenantByField($field, $value);
                });
            }
        } catch (\BadMethodCallException $e) {
            // Cache driver doesn't support tags, fall through to regular caching
        }
        
        // Fall back to regular caching without tags (for file, database, array drivers)
        return \Cache::remember($cacheKey, $ttl, function () use ($field, $value) {
            return $this->queryTenantByField($field, $value);
        });
    }

    /**
     * Query tenant by field and value.
     * 
     * @param string $field
     * @param string $value
     * @return Tenant|null
     */
    protected function queryTenantByField(string $field, string $value): ?Tenant
    {
        $query = Tenant::query()->active();
        
        if ($field === 'id') {
            return $query->whereKey($value)->first();
        }
        
        return $query->where($field, $value)->first();
    }

    /**
     * Check if subdomain is reserved.
     * 
     * @param string $subdomain
     * @return bool
     */
    protected function isReservedSubdomain(string $subdomain): bool
    {
        $reserved = config('tenancy.reserved_subdomains', []);
        return in_array(strtolower($subdomain), array_map('strtolower', $reserved), true);
    }
}

