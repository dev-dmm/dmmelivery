<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Shipment;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Courier;
use App\Models\PredictiveEta;
use App\Models\Alert;
use App\Services\WebSocketService;
use App\Services\AnalyticsService;
use App\Services\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Mockery;

class ShipmentWorkflowTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_complete_full_shipment_workflow()
    {
        // 1. Create shipment
        $shipmentData = [
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
            'tracking_number' => 'TRK123456',
            'weight' => 1.5,
            'shipping_address' => '123 Main St, City, Country',
            'shipping_cost' => 10.50,
        ];

        $response = $this->postJson('/api/v1/shipments', $shipmentData);
        $response->assertStatus(201);
        
        $shipment = Shipment::where('tracking_number', 'TRK123456')->first();
        $this->assertNotNull($shipment);

        // 2. Update shipment status to picked up
        $response = $this->postJson("/api/v1/shipments/{$shipment->id}/update-status", [
            'status' => 'picked_up',
            'description' => 'Package picked up from origin',
            'location' => 'Origin Warehouse',
        ]);
        $response->assertStatus(200);

        // 3. Update to in transit
        $response = $this->postJson("/api/v1/shipments/{$shipment->id}/update-status", [
            'status' => 'in_transit',
            'description' => 'Package in transit to destination',
            'location' => 'Distribution Center',
        ]);
        $response->assertStatus(200);

        // 4. Update to out for delivery
        $response = $this->postJson("/api/v1/shipments/{$shipment->id}/update-status", [
            'status' => 'out_for_delivery',
            'description' => 'Package out for delivery',
            'location' => 'Local Delivery Hub',
        ]);
        $response->assertStatus(200);

        // 5. Mark as delivered
        $response = $this->postJson("/api/v1/shipments/{$shipment->id}/update-status", [
            'status' => 'delivered',
            'description' => 'Package delivered successfully',
            'location' => 'Customer Address',
        ]);
        $response->assertStatus(200);

        // 6. Verify status history
        $response = $this->getJson("/api/v1/shipments/{$shipment->id}/history");
        $response->assertStatus(200);
        
        $history = $response->json('data');
        $this->assertCount(4, $history); // pending, picked_up, in_transit, out_for_delivery, delivered

        // 7. Verify final shipment status
        $shipment->refresh();
        $this->assertEquals('delivered', $shipment->status);
    }

    /** @test */
    public function it_can_handle_webhook_updates()
    {
        // Create shipment
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
            'tracking_number' => 'TRK789012',
            'status' => 'pending',
        ]);

        // Simulate webhook update
        $webhookData = [
            'tracking_number' => 'TRK789012',
            'status' => 'in_transit',
            'description' => 'Package picked up by courier',
            'location' => 'Origin Warehouse',
            'happened_at' => now()->toISOString(),
        ];

        $response = $this->postJson('/api/v1/public/webhooks/shipment-update', $webhookData);
        $response->assertStatus(200);

        // Verify shipment was updated
        $shipment->refresh();
        $this->assertEquals('in_transit', $shipment->status);

        // Verify status history was created
        $this->assertDatabaseHas('shipment_status_histories', [
            'shipment_id' => $shipment->id,
            'status' => 'in_transit',
            'description' => 'Package picked up by courier',
        ]);
    }

    /** @test */
    public function it_can_track_public_shipment_status()
    {
        // Create delivered shipment
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
            'tracking_number' => 'TRK456789',
            'status' => 'delivered',
            'estimated_delivery' => now()->subDay(),
            'actual_delivery' => now()->subHour(),
        ]);

        // Test public status endpoint (no authentication required)
        $response = $this->getJson("/api/v1/public/shipments/{$shipment->tracking_number}/status");
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals('TRK456789', $data['tracking_number']);
        $this->assertEquals('delivered', $data['status']);
        $this->assertNotNull($data['actual_delivery']);
    }

    /** @test */
    public function it_can_generate_analytics_for_shipments()
    {
        // Create multiple shipments with different statuses
        Shipment::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
            'status' => 'delivered',
            'created_at' => now()->subDays(5),
        ]);

        Shipment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
            'created_at' => now()->subDays(2),
        ]);

        Shipment::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
            'status' => 'failed',
            'created_at' => now()->subDay(),
        ]);

        // Test analytics endpoint
        $response = $this->getJson('/api/v1/analytics/dashboard');
        $response->assertStatus(200);
        
        $analytics = $response->json('data');
        $this->assertArrayHasKey('overview', $analytics);
        $this->assertArrayHasKey('performance', $analytics);
        $this->assertArrayHasKey('trends', $analytics);
        
        $overview = $analytics['overview'];
        $this->assertEquals(10, $overview['total_shipments']);
        $this->assertEquals(5, $overview['delivered_shipments']);
        $this->assertEquals(50.0, $overview['success_rate']);
    }

    /** @test */
    public function it_can_handle_predictive_eta_workflow()
    {
        // Create shipment
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
        ]);

        // Create predictive ETA
        $predictiveEta = PredictiveEta::factory()->create([
            'shipment_id' => $shipment->id,
            'tenant_id' => $this->tenant->id,
            'predicted_eta' => now()->addDays(2),
            'confidence_score' => 0.85,
            'delay_risk_level' => 'low',
        ]);

        // Test analytics with predictions
        $response = $this->getJson('/api/v1/analytics/predictions');
        $response->assertStatus(200);
        
        $predictions = $response->json('data');
        $this->assertArrayHasKey('accuracy_score', $predictions);
        $this->assertArrayHasKey('model_performance', $predictions);
    }

    /** @test */
    public function it_can_handle_alert_workflow()
    {
        // Create shipment
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
        ]);

        // Create alert
        $alert = Alert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'shipment_id' => $shipment->id,
            'alert_type' => 'delay',
            'severity_level' => 'high',
            'title' => 'Delivery Delay Alert',
            'description' => 'Shipment is experiencing delays',
        ]);

        // Test analytics with alerts
        $response = $this->getJson('/api/v1/analytics/alerts');
        $response->assertStatus(200);
        
        $alerts = $response->json('data');
        $this->assertEquals(1, $alerts['total_alerts']);
        $this->assertArrayHasKey('alert_types', $alerts);
        $this->assertArrayHasKey('severity_distribution', $alerts);
    }

    /** @test */
    public function it_can_handle_geographic_analytics()
    {
        // Create shipments with different addresses
        Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'shipping_address' => 'New York, NY, USA',
            'status' => 'delivered',
        ]);

        Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'shipping_address' => 'Los Angeles, CA, USA',
            'status' => 'delivered',
        ]);

        Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'shipping_address' => 'Chicago, IL, USA',
            'status' => 'in_transit',
        ]);

        // Test geographic analytics
        $response = $this->getJson('/api/v1/analytics/geographic');
        $response->assertStatus(200);
        
        $geographic = $response->json('data');
        $this->assertArrayHasKey('top_destinations', $geographic);
        $this->assertArrayHasKey('geographic_performance', $geographic);
        $this->assertCount(3, $geographic['top_destinations']);
    }

    /** @test */
    public function it_can_handle_customer_analytics()
    {
        // Create multiple customers with different shipment counts
        $customer1 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer2 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $order1 = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer1->id]);
        $order2 = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer2->id]);

        // Create shipments for different customers
        Shipment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order1->id,
            'customer_id' => $customer1->id,
            'status' => 'delivered',
        ]);

        Shipment::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order2->id,
            'customer_id' => $customer2->id,
            'status' => 'delivered',
        ]);

        // Test customer analytics
        $response = $this->getJson('/api/v1/analytics/customers');
        $response->assertStatus(200);
        
        $customers = $response->json('data');
        $this->assertArrayHasKey('top_customers', $customers);
        $this->assertArrayHasKey('customer_retention', $customers);
        $this->assertCount(2, $customers['top_customers']);
    }

    /** @test */
    public function it_can_handle_courier_analytics()
    {
        // Create multiple couriers
        $courier1 = Courier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Courier A']);
        $courier2 = Courier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Courier B']);

        // Create shipments for different couriers
        Shipment::factory()->count(4)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'courier_id' => $courier1->id,
            'status' => 'delivered',
        ]);

        Shipment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'courier_id' => $courier2->id,
            'status' => 'delivered',
        ]);

        // Test courier analytics
        $response = $this->getJson('/api/v1/analytics/couriers');
        $response->assertStatus(200);
        
        $couriers = $response->json('data');
        $this->assertArrayHasKey('courier_performance', $couriers);
        $this->assertArrayHasKey('courier_rankings', $couriers);
        $this->assertCount(2, $couriers['courier_performance']);
    }

    /** @test */
    public function it_can_export_analytics_data()
    {
        // Create test data
        Shipment::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'status' => 'delivered',
        ]);

        // Test export endpoint
        $response = $this->postJson('/api/v1/analytics/export', [
            'format' => 'json',
            'start_date' => now()->subDays(30),
            'end_date' => now(),
        ]);
        
        $response->assertStatus(200);
        $this->assertArrayHasKey('data', $response->json());
    }

    /** @test */
    public function it_can_handle_api_documentation()
    {
        $response = $this->getJson('/api/docs');
        $response->assertStatus(200);
        
        $docs = $response->json('data');
        $this->assertArrayHasKey('api_info', $docs);
        $this->assertArrayHasKey('endpoints', $docs);
        $this->assertArrayHasKey('authentication', $docs);
        $this->assertArrayHasKey('rate_limits', $docs);
        $this->assertArrayHasKey('error_codes', $docs);
        $this->assertArrayHasKey('examples', $docs);
    }

    /** @test */
    public function it_can_handle_websocket_authentication()
    {
        $response = $this->postJson('/api/v1/websocket/authenticate', [
            'socket_id' => '123.456',
            'channel_name' => "dmmelivery_tenant_{$this->tenant->id}",
        ]);
        
        $response->assertStatus(200);
        $this->assertArrayHasKey('auth', $response->json());
    }

    /** @test */
    public function it_can_get_websocket_channels()
    {
        $response = $this->getJson('/api/v1/websocket/channels');
        $response->assertStatus(200);
        
        $channels = $response->json('channels');
        $this->assertArrayHasKey('tenant', $channels);
        $this->assertArrayHasKey('user', $channels);
        $this->assertEquals("tenant_{$this->tenant->id}", $channels['tenant']);
        $this->assertEquals("user_{$this->user->id}", $channels['user']);
    }

    /** @test */
    public function it_can_test_websocket_connection()
    {
        $response = $this->getJson('/api/v1/websocket/test');
        $response->assertStatus(200);
        
        $this->assertArrayHasKey('connected', $response->json());
        $this->assertArrayHasKey('timestamp', $response->json());
    }
}
