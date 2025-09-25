<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Shipment;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Courier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class ShipmentApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Tenant $tenant;
    private Customer $customer;
    private Courier $courier;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->courier = Courier::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_list_shipments()
    {
        Shipment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
        ]);

        $response = $this->getJson('/api/v1/shipments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'tracking_number',
                        'status',
                        'created_at',
                        'customer',
                        'courier',
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'has_more',
                ]
            ]);
    }

    /** @test */
    public function it_can_filter_shipments_by_status()
    {
        Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'status' => 'delivered',
        ]);

        Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/shipments?status=delivered');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('delivered', $response->json('data.0.status'));
    }

    /** @test */
    public function it_can_create_a_shipment()
    {
        $shipmentData = [
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
            'tracking_number' => 'TRK123456',
            'weight' => 1.5,
            'shipping_address' => '123 Main St, City, Country',
            'shipping_cost' => 10.50,
        ];

        $response = $this->postJson('/api/v1/shipments', $shipmentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'tracking_number',
                    'status',
                    'created_at',
                ]
            ]);

        $this->assertDatabaseHas('shipments', [
            'tracking_number' => 'TRK123456',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_shipment()
    {
        $response = $this->postJson('/api/v1/shipments', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_id', 'shipping_address']);
    }

    /** @test */
    public function it_can_show_a_shipment()
    {
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
        ]);

        $response = $this->getJson("/api/v1/shipments/{$shipment->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'tracking_number',
                    'status',
                    'customer',
                    'courier',
                    'order',
                ]
            ]);
    }

    /** @test */
    public function it_cannot_show_shipment_from_different_tenant()
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        
        $shipment = Shipment::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->getJson("/api/v1/shipments/{$shipment->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_can_update_a_shipment()
    {
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'status' => 'pending',
        ]);

        $updateData = [
            'status' => 'in_transit',
            'tracking_number' => 'TRK789012',
        ];

        $response = $this->putJson("/api/v1/shipments/{$shipment->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Shipment updated successfully',
            ]);

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'status' => 'in_transit',
            'tracking_number' => 'TRK789012',
        ]);
    }

    /** @test */
    public function it_can_delete_a_shipment()
    {
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
        ]);

        $response = $this->deleteJson("/api/v1/shipments/{$shipment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Shipment deleted successfully',
            ]);

        $this->assertSoftDeleted('shipments', [
            'id' => $shipment->id,
        ]);
    }

    /** @test */
    public function it_can_get_tracking_details()
    {
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
        ]);

        $response = $this->getJson("/api/v1/shipments/{$shipment->id}/tracking");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'shipment',
                    'current_status',
                    'status_history',
                    'predictive_eta',
                    'alerts',
                ]
            ]);
    }

    /** @test */
    public function it_can_update_shipment_status()
    {
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'status' => 'pending',
        ]);

        $statusData = [
            'status' => 'in_transit',
            'description' => 'Package picked up from origin',
            'location' => 'Origin Warehouse',
        ];

        $response = $this->postJson("/api/v1/shipments/{$shipment->id}/update-status", $statusData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Shipment status updated successfully',
            ]);

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'status' => 'in_transit',
        ]);

        $this->assertDatabaseHas('shipment_status_histories', [
            'shipment_id' => $shipment->id,
            'status' => 'in_transit',
            'description' => 'Package picked up from origin',
        ]);
    }

    /** @test */
    public function it_can_get_status_history()
    {
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
        ]);

        // Create status history entries
        $shipment->statusHistory()->create([
            'status' => 'pending',
            'description' => 'Shipment created',
            'happened_at' => now()->subHours(2),
        ]);

        $shipment->statusHistory()->create([
            'status' => 'in_transit',
            'description' => 'Package in transit',
            'happened_at' => now()->subHour(),
        ]);

        $response = $this->getJson("/api/v1/shipments/{$shipment->id}/history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'status',
                        'description',
                        'happened_at',
                    ]
                ]
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function it_can_get_public_shipment_status()
    {
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'tracking_number' => 'TRK123456',
            'status' => 'in_transit',
        ]);

        $response = $this->getJson("/api/v1/public/shipments/{$shipment->tracking_number}/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tracking_number',
                    'status',
                    'current_location',
                    'estimated_delivery',
                    'courier',
                    'last_updated',
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_public_shipment()
    {
        $response = $this->getJson('/api/v1/public/shipments/NONEXISTENT/status');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Shipment not found',
                'error_code' => 'SHIPMENT_NOT_FOUND',
            ]);
    }

    /** @test */
    public function it_can_handle_webhook_updates()
    {
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'tracking_number' => 'TRK123456',
            'status' => 'pending',
        ]);

        $webhookData = [
            'tracking_number' => 'TRK123456',
            'status' => 'in_transit',
            'description' => 'Package picked up',
            'location' => 'Origin Warehouse',
        ];

        $response = $this->postJson('/api/v1/public/webhooks/shipment-update', $webhookData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);

        $this->assertDatabaseHas('shipments', [
            'tracking_number' => 'TRK123456',
            'status' => 'in_transit',
        ]);
    }

    /** @test */
    public function it_validates_webhook_data()
    {
        $response = $this->postJson('/api/v1/public/webhooks/shipment-update', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tracking_number', 'status']);
    }

    /** @test */
    public function it_requires_authentication_for_protected_endpoints()
    {
        // Remove authentication
        auth()->logout();

        $response = $this->getJson('/api/v1/shipments');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_paginates_shipments_correctly()
    {
        Shipment::factory()->count(25)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
        ]);

        $response = $this->getJson('/api/v1/shipments?per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(25, $response->json('pagination.total'));
        $this->assertEquals(3, $response->json('pagination.last_page'));
    }
}
