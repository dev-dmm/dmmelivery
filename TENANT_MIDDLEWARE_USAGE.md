# Tenant Middleware Usage Guide

## Overview

The tenant middleware system provides flexible tenant identification for web routes and strict enforcement for API routes, with a unified resolver, events, and queue support.

## Middleware

### IdentifyTenant (Web Routes)

Flexible tenant identification for web routes with flag-based configuration.

**Basic Usage:**
```php
Route::middleware(['identify.tenant'])->group(function () {
    // Enforces tenant presence
});
```

**With Flags:**
```php
// Allow guest access
Route::middleware(['identify.tenant:allow-guest'])->group(function () {
    // Login page, public pages
});

// Allow routes without tenant
Route::middleware(['identify.tenant:allow-tenantless'])->group(function () {
    // Profile routes, settings
});

// Allow super-admin bypass
Route::middleware(['identify.tenant:allow-superadmin'])->group(function () {
    // Admin routes
});

// Combine flags
Route::middleware(['identify.tenant:allow-guest,allow-tenantless'])->group(function () {
    // Flexible routes
});
```

### EnforceTenant (API Routes)

Strict tenant enforcement for API routes. Always requires authentication and valid tenant.

**Usage:**
```php
Route::middleware(['auth:sanctum', 'enforce.tenant'])->group(function () {
    // All routes require valid tenant
});
```

## Helper Functions

### `tenant()`

Get the current tenant instance:

```php
$tenant = tenant();
if ($tenant) {
    echo $tenant->name;
}
```

### `tenant_id()`

Get the current tenant ID:

```php
$tenantId = tenant_id();
// Use in queries, logs, etc.
```

## Events

### TenantResolved

Fired when a tenant is successfully resolved and bound.

**Listener Example:**
```php
use App\Events\TenantResolved;
use Illuminate\Support\Facades\Event;

Event::listen(TenantResolved::class, function ($event) {
    $tenant = $event->tenant;
    
    // Switch database connection
    // Configure URL roots
    // Set feature flags
    // Initialize tenant-specific services
});
```

### TenantCleared

Fired when tenant context is cleared.

**Listener Example:**
```php
use App\Events\TenantCleared;

Event::listen(TenantCleared::class, function ($event) {
    // Reset database connections
    // Clear tenant-specific caches
    // Clean up resources
});
```

## Queue Jobs

### Using BindTenant Middleware

For jobs that need tenant context:

```php
use App\Queue\Middleware\BindTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessShipment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?string $tenantId = null;

    public function __construct()
    {
        // Capture tenant ID at dispatch time
        $this->tenantId = tenant_id();
    }

    public function middleware(): array
    {
        return [new BindTenant()];
    }

    public function handle()
    {
        // Tenant is now bound - use tenant() helper
        $tenant = tenant();
        
        // Your job logic here
    }
}
```

## Rate Limiting

Tenant-aware rate limiting is configured in `bootstrap/app.php`:

- **Authenticated API**: 120 requests/minute per tenant+user
- **Unauthenticated API**: 60 requests/minute per IP

Each tenant gets its own rate limit bucket, preventing one tenant from affecting another.

## Tenant Resolution Priority

The unified resolver checks in this order:

1. **Super-admin override** (X-Tenant-ID header or tenant_id query)
   - Requires HTTPS in production
   - Only accepts UUIDs
   - Audit logged
   
2. **Route parameter** `{tenant}`
   - Can be ID or subdomain
   
3. **Subdomain** (acme.yourapp.com)
   - Requires TrustedProxy configuration if behind load balancer
   
4. **Authenticated user's tenant**

## Security Features

- **HTTPS-only overrides**: Super-admin tenant overrides only work over HTTPS in production
- **UUID validation**: Only valid UUIDs accepted for tenant overrides
- **Audit logging**: All tenant overrides are logged with admin info, IP, and target tenant
- **Memoization**: Tenant resolution is cached per request to prevent duplicate queries
- **Side-effect free trait**: Core logic doesn't mutate auth/session state

## Testing

See `tests/Feature/TenantMiddlewareTest.php` for comprehensive test examples covering:
- API authentication
- Invalid tenant handling
- Valid tenant binding
- Guest access
- Session invalidation
- Super-admin overrides
- Memoization
- Inactive tenant rejection

## Long-term Architecture

The event-driven design allows for easy extension:

- **Multi-database per tenant**: Listen to `TenantResolved` and switch DB connections
- **Schema per tenant**: Configure schema in event listener
- **Feature flags**: Set tenant-specific features on resolution
- **URL generation**: Configure subdomain URLs per tenant

All tenant-specific logic is isolated in event listeners, keeping middleware and controllers clean.

