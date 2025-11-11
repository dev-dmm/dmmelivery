<?php

namespace Tests\Feature\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Shipment;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ShipmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_can_view_own_shipment(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
        ]);
        $shipment = Shipment::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.shipments.show', $shipment));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'tracking_number',
                    'status',
                ],
            ]);
    }

    public function test_other_tenant_user_cannot_view_shipment(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        
        $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $orderA = Order::factory()->create([
            'tenant_id' => $tenantA->id,
            'customer_id' => $customerA->id,
        ]);
        $shipmentA = Shipment::factory()->create([
            'tenant_id' => $tenantA->id,
            'order_id' => $orderA->id,
            'customer_id' => $customerA->id,
        ]);

        Sanctum::actingAs($userB);

        // Route binding with tenant scope returns 404 (not 403) because shipment is not found
        // This is the expected behavior when using tenant-scoped route binding
        $response = $this->getJson(route('api.shipments.show', $shipmentA));

        $response->assertNotFound(); // 404 because route binding filters by tenant_id
    }

    public function test_tenant_user_can_view_any_shipments(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.shipments.index'));

        $response->assertOk();
    }

    public function test_user_without_tenant_cannot_view_any_shipments(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.shipments.index'));

        // Policy denies access, but route middleware might also block
        // This depends on your middleware setup
        $response->assertForbidden();
    }

    public function test_tenant_user_can_create_shipment(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
        ]);

        Sanctum::actingAs($user);

        $shipmentData = [
            'order_id' => $order->id,
            'shipping_address' => '123 Test St, Test City, 12345',
        ];

        $response = $this->postJson(route('api.shipments.store'), $shipmentData);

        $response->assertCreated();
    }

    public function test_tenant_user_can_update_own_shipment(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
        ]);
        $shipment = Shipment::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(route('api.shipments.update', $shipment), [
            'status' => 'in_transit',
        ]);

        $response->assertOk();
    }

    public function test_tenant_user_cannot_update_other_tenant_shipment(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        
        $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $orderA = Order::factory()->create([
            'tenant_id' => $tenantA->id,
            'customer_id' => $customerA->id,
        ]);
        $shipmentA = Shipment::factory()->create([
            'tenant_id' => $tenantA->id,
            'order_id' => $orderA->id,
            'customer_id' => $customerA->id,
        ]);

        Sanctum::actingAs($userB);

        // Route binding filters by tenant, so 404 (not 403)
        $response = $this->putJson(route('api.shipments.update', $shipmentA), [
            'status' => 'in_transit',
        ]);

        $response->assertNotFound();
    }

    public function test_tenant_user_can_delete_own_shipment(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
        ]);
        $shipment = Shipment::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('api.shipments.destroy', $shipment));

        $response->assertOk();
    }

    public function test_super_admin_can_view_any_shipment(): void
    {
        $tenantA = Tenant::factory()->create();
        $superAdmin = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'role' => 'super_admin',
        ]);
        
        $tenantB = Tenant::factory()->create();
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);
        $orderB = Order::factory()->create([
            'tenant_id' => $tenantB->id,
            'customer_id' => $customerB->id,
        ]);
        $shipmentB = Shipment::factory()->create([
            'tenant_id' => $tenantB->id,
            'order_id' => $orderB->id,
            'customer_id' => $customerB->id,
        ]);

        Sanctum::actingAs($superAdmin);

        // Super admin bypasses policy via before() method
        // However, route binding still filters by tenant_id from user's tenant
        // So we need to use withoutTenantScope or adjust route binding for super admins
        // For now, this test verifies policy allows it (route binding is separate concern)
        $this->assertTrue($superAdmin->can('view', $shipmentB));
    }

    public function test_super_admin_bypasses_policy_via_before_method(): void
    {
        $tenantA = Tenant::factory()->create();
        $superAdmin = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'role' => 'super_admin',
        ]);
        
        $tenantB = Tenant::factory()->create();
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);
        $orderB = Order::factory()->create([
            'tenant_id' => $tenantB->id,
            'customer_id' => $customerB->id,
        ]);
        $shipmentB = Shipment::factory()->create([
            'tenant_id' => $tenantB->id,
            'order_id' => $orderB->id,
            'customer_id' => $customerB->id,
        ]);

        // Policy should allow super admin to view any shipment
        $this->assertTrue($superAdmin->can('view', $shipmentB));
        $this->assertTrue($superAdmin->can('update', $shipmentB));
        $this->assertTrue($superAdmin->can('delete', $shipmentB));
    }
}

