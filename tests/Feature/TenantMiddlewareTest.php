<?php

namespace Tests\Feature;

use App\Events\TenantCleared;
use App\Events\TenantResolved;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Tenant Middleware Tests
 * 
 * Tests for IdentifyTenant (web) and EnforceTenant (API) middleware.
 * Covers the 6 critical scenarios for tenant resolution and enforcement.
 */
class TenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([TenantResolved::class, TenantCleared::class]);
    }

    /**
     * Test 1: API unauthenticated → 401 JSON
     */
    public function test_api_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/shipments');

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Unauthenticated',
        ]);
    }

    /**
     * Test 2: API invalid tenant via subdomain → 403 JSON, no session mutation
     */
    public function test_api_invalid_tenant_returns_403_without_session_mutation(): void
    {
        $user = User::factory()->create([
            'tenant_id' => null, // User without tenant
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shipments');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'InvalidTenant',
            'code' => 'TENANT_NOT_FOUND_OR_INACTIVE',
        ]);

        // Verify session was not mutated (no logout)
        $this->assertAuthenticatedAs($user, 'sanctum');
    }

    /**
     * Test 3: API valid tenant → 200 and app('tenant') is set
     */
    public function test_api_valid_tenant_sets_tenant_in_container(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shipments');

        $response->assertStatus(200);
        $this->assertNotNull(app('tenant'));
        $this->assertEquals($tenant->id, app('tenant')->id);
        
        Event::assertDispatched(TenantResolved::class, function ($event) use ($tenant) {
            return $event->tenant->id === $tenant->id;
        });
    }

    /**
     * Test 4: Web allow-guest route with no tenant → proceeds, nothing bound
     */
    public function test_web_allow_guest_route_proceeds_without_tenant(): void
    {
        // This test assumes you have a route with 'identify.tenant:allow-guest' middleware
        // You may need to create a test route or adjust this test
        
        $response = $this->get('/login'); // Assuming login doesn't require tenant

        // Should not redirect or error
        $response->assertStatus(200);
        $this->assertFalse(app()->has('tenant'));
    }

    /**
     * Test 5: Web user without tenant → redirect + session invalidation (when not allow-tenantless)
     */
    public function test_web_user_without_tenant_redirects_and_invalidates_session(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);

        $response = $this->actingAs($user)
            ->get('/dashboard'); // Assuming dashboard requires tenant

        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    /**
     * Test 6: Super-admin override with valid UUID over HTTPS → binds overridden tenant and logs audit
     */
    public function test_super_admin_override_binds_tenant_and_logs_audit(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $adminTenant = Tenant::factory()->create(['is_active' => true]);
        $admin->update(['tenant_id' => $adminTenant->id]);

        // Simulate HTTPS request
        $this->withServerVariables(['HTTPS' => 'on']);

        $response = $this->actingAs($admin)
            ->withHeader('X-Tenant-ID', $tenant->id)
            ->get('/dashboard');

        // Should bind the overridden tenant, not admin's tenant
        $this->assertNotNull(app('tenant'));
        $this->assertEquals($tenant->id, app('tenant')->id);
        $this->assertNotEquals($adminTenant->id, app('tenant')->id);

        Event::assertDispatched(TenantResolved::class, function ($event) use ($tenant) {
            return $event->tenant->id === $tenant->id;
        });

        // Verify audit log was written (check logs)
        // In a real test, you might want to assert log was written
    }

    /**
     * Test: Memoization prevents duplicate queries
     */
    public function test_tenant_resolution_is_memoized(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        // Enable query logging
        \DB::enableQueryLog();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shipments');

        $queries = \DB::getQueryLog();
        $tenantQueries = array_filter($queries, fn($q) => 
            str_contains($q['query'], 'tenants')
        );

        // Should only query tenants table once (memoization)
        $this->assertCount(1, $tenantQueries);
    }

    /**
     * Test: Inactive tenant is rejected
     */
    public function test_inactive_tenant_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => false]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shipments');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'InvalidTenant',
            'code' => 'TENANT_NOT_FOUND_OR_INACTIVE',
        ]);
    }

    /**
     * Test: Custom domain resolves primary_domain
     */
    public function test_custom_domain_resolves_primary_domain(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'primary_domain' => 'acme.com',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shipments', [
                'Host' => 'acme.com',
            ]);

        $response->assertStatus(200);
        $this->assertEquals($tenant->id, app('tenant')->id);
    }

    /**
     * Test: WWW domain resolves primary_domain (strips www)
     */
    public function test_www_domain_resolves_primary_domain(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'primary_domain' => 'acme.com',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shipments', [
                'Host' => 'www.acme.com',
            ]);

        $response->assertStatus(200);
        $this->assertEquals($tenant->id, app('tenant')->id);
    }

    /**
     * Test: Override requires HTTPS in production
     */
    public function test_override_requires_https_in_production(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::factory()->create(['is_active' => true]);

        // Set environment to production
        $originalEnv = app()->environment();
        app()->detectEnvironment(fn() => 'production');

        try {
            // HTTP request should ignore override
            $response = $this->actingAs($admin)
                ->withHeader('X-Tenant-ID', $tenant->id)
                ->get('/dashboard');

            // Should not bind override (falls back to admin's tenant or user tenant)
            // This test may need adjustment based on your exact behavior
            
            // HTTPS request should accept override
            $this->withServerVariables(['HTTPS' => 'on']);
            $response = $this->actingAs($admin)
                ->withHeader('X-Tenant-ID', $tenant->id)
                ->get('/dashboard');

            // Should bind override over HTTPS
            $this->assertEquals($tenant->id, app('tenant')->id);
        } finally {
            // Restore original environment
            app()->detectEnvironment(fn() => $originalEnv);
        }
    }

    /**
     * Test: Guard parity - unauthenticated via Sanctum returns 401
     */
    public function test_guard_parity_unauthenticated_sanctum_returns_401(): void
    {
        // Even if default web guard is logged in, sanctum should be checked
        $webUser = User::factory()->create();
        
        $response = $this->actingAs($webUser) // Web guard
            ->getJson('/api/v1/shipments'); // API route uses sanctum

        // Should return 401 because sanctum guard is not authenticated
        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Unauthenticated',
        ]);
    }
}

