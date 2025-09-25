<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AnalyticsService;
use App\Services\CacheService;
use App\Models\Tenant;
use App\Models\Shipment;
use App\Models\Customer;
use App\Models\Courier;
use App\Models\Order;
use App\Models\PredictiveEta;
use App\Models\Alert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private AnalyticsService $analyticsService;
    private Tenant $tenant;
    private CacheService $mockCacheService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->mockCacheService = Mockery::mock(CacheService::class);
        $this->analyticsService = new AnalyticsService($this->mockCacheService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_tenant_analytics()
    {
        // Mock cache service
        $this->mockCacheService->shouldReceive('remember')
            ->once()
            ->andReturn([
                'overview' => [],
                'performance' => [],
                'trends' => [],
                'predictions' => [],
                'alerts' => [],
                'geographic' => [],
                'customer' => [],
                'courier' => [],
            ]);

        $analytics = $this->analyticsService->getTenantAnalytics($this->tenant->id, []);

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('overview', $analytics);
        $this->assertArrayHasKey('performance', $analytics);
        $this->assertArrayHasKey('trends', $analytics);
        $this->assertArrayHasKey('predictions', $analytics);
        $this->assertArrayHasKey('alerts', $analytics);
        $this->assertArrayHasKey('geographic', $analytics);
        $this->assertArrayHasKey('customer', $analytics);
        $this->assertArrayHasKey('courier', $analytics);
    }

    /** @test */
    public function it_calculates_overview_metrics_correctly()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $courier = Courier::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        // Create test shipments
        Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
            'status' => 'delivered',
            'created_at' => now()->subDays(5),
        ]);

        Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
            'status' => 'in_transit',
            'created_at' => now()->subDays(3),
        ]);

        Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
            'status' => 'failed',
            'created_at' => now()->subDays(1),
        ]);

        $this->mockCacheService->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $analytics = $this->analyticsService->getTenantAnalytics($this->tenant->id, [
            'start_date' => now()->subDays(30),
            'end_date' => now(),
        ]);

        $overview = $analytics['overview'];
        
        $this->assertEquals(3, $overview['total_shipments']);
        $this->assertEquals(1, $overview['delivered_shipments']);
        $this->assertEquals(1, $overview['in_transit_shipments']);
        $this->assertEquals(1, $overview['failed_shipments']);
        $this->assertEquals(33.33, $overview['success_rate'], '', 2);
    }

    /** @test */
    public function it_calculates_performance_metrics()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $courier = Courier::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        // Create delivered shipment with timing data
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
            'status' => 'delivered',
            'created_at' => now()->subDays(2),
            'estimated_delivery' => now()->subDay(),
            'actual_delivery' => now()->subHours(12),
        ]);

        $this->mockCacheService->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $analytics = $this->analyticsService->getTenantAnalytics($this->tenant->id, [
            'start_date' => now()->subDays(30),
            'end_date' => now(),
        ]);

        $performance = $analytics['performance'];
        
        $this->assertArrayHasKey('delivery_times', $performance);
        $this->assertArrayHasKey('on_time_rate', $performance);
        $this->assertArrayHasKey('performance_score', $performance);
    }

    /** @test */
    public function it_calculates_trend_analysis()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $courier = Courier::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        // Create shipments for different days
        for ($i = 0; $i < 5; $i++) {
            Shipment::factory()->create([
                'tenant_id' => $this->tenant->id,
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'courier_id' => $courier->id,
                'status' => 'delivered',
                'created_at' => now()->subDays($i),
            ]);
        }

        $this->mockCacheService->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $analytics = $this->analyticsService->getTenantAnalytics($this->tenant->id, [
            'start_date' => now()->subDays(7),
            'end_date' => now(),
            'period' => 'daily',
        ]);

        $trends = $analytics['trends'];
        
        $this->assertArrayHasKey('periods', $trends);
        $this->assertArrayHasKey('shipments', $trends);
        $this->assertArrayHasKey('delivered', $trends);
        $this->assertArrayHasKey('trend_direction', $trends);
    }

    /** @test */
    public function it_calculates_predictive_analytics()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $courier = Courier::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
        ]);

        // Create predictive ETA
        PredictiveEta::factory()->create([
            'shipment_id' => $shipment->id,
            'tenant_id' => $this->tenant->id,
            'confidence_score' => 0.85,
        ]);

        $this->mockCacheService->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $analytics = $this->analyticsService->getTenantAnalytics($this->tenant->id, [
            'start_date' => now()->subDays(30),
            'end_date' => now(),
        ]);

        $predictions = $analytics['predictions'];
        
        $this->assertArrayHasKey('accuracy_score', $predictions);
        $this->assertArrayHasKey('confidence_trend', $predictions);
        $this->assertArrayHasKey('delay_predictions', $predictions);
        $this->assertArrayHasKey('model_performance', $predictions);
    }

    /** @test */
    public function it_calculates_alert_analytics()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $courier = Courier::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);
        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
        ]);

        // Create alerts
        Alert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'shipment_id' => $shipment->id,
            'alert_type' => 'delay',
            'severity_level' => 'high',
            'triggered_at' => now()->subDays(2),
        ]);

        Alert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'shipment_id' => $shipment->id,
            'alert_type' => 'exception',
            'severity_level' => 'medium',
            'triggered_at' => now()->subDay(),
        ]);

        $this->mockCacheService->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $analytics = $this->analyticsService->getTenantAnalytics($this->tenant->id, [
            'start_date' => now()->subDays(30),
            'end_date' => now(),
        ]);

        $alerts = $analytics['alerts'];
        
        $this->assertEquals(2, $alerts['total_alerts']);
        $this->assertArrayHasKey('alert_types', $alerts);
        $this->assertArrayHasKey('severity_distribution', $alerts);
        $this->assertArrayHasKey('avg_resolution_time', $alerts);
        $this->assertArrayHasKey('alert_trends', $alerts);
    }

    /** @test */
    public function it_calculates_geographic_analytics()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $courier = Courier::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        // Create shipments with different addresses
        Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
            'shipping_address' => 'New York, NY, USA',
            'status' => 'delivered',
        ]);

        Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
            'shipping_address' => 'Los Angeles, CA, USA',
            'status' => 'delivered',
        ]);

        $this->mockCacheService->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $analytics = $this->analyticsService->getTenantAnalytics($this->tenant->id, [
            'start_date' => now()->subDays(30),
            'end_date' => now(),
        ]);

        $geographic = $analytics['geographic'];
        
        $this->assertArrayHasKey('top_destinations', $geographic);
        $this->assertArrayHasKey('geographic_performance', $geographic);
        $this->assertCount(2, $geographic['top_destinations']);
    }

    /** @test */
    public function it_calculates_customer_analytics()
    {
        $customer1 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer2 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $courier = Courier::factory()->create(['tenant_id' => $this->tenant->id]);
        $order1 = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer1->id]);
        $order2 = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer2->id]);

        // Create shipments for different customers
        Shipment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order1->id,
            'customer_id' => $customer1->id,
            'courier_id' => $courier->id,
            'status' => 'delivered',
        ]);

        Shipment::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order2->id,
            'customer_id' => $customer2->id,
            'courier_id' => $courier->id,
            'status' => 'delivered',
        ]);

        $this->mockCacheService->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $analytics = $this->analyticsService->getTenantAnalytics($this->tenant->id, [
            'start_date' => now()->subDays(30),
            'end_date' => now(),
        ]);

        $customer = $analytics['customer'];
        
        $this->assertArrayHasKey('top_customers', $customer);
        $this->assertArrayHasKey('customer_retention', $customer);
        $this->assertArrayHasKey('customer_satisfaction', $customer);
        $this->assertCount(2, $customer['top_customers']);
    }

    /** @test */
    public function it_calculates_courier_analytics()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $courier1 = Courier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Courier A']);
        $courier2 = Courier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Courier B']);
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        // Create shipments for different couriers
        Shipment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier1->id,
            'status' => 'delivered',
        ]);

        Shipment::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier2->id,
            'status' => 'delivered',
        ]);

        $this->mockCacheService->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $analytics = $this->analyticsService->getTenantAnalytics($this->tenant->id, [
            'start_date' => now()->subDays(30),
            'end_date' => now(),
        ]);

        $courier = $analytics['courier'];
        
        $this->assertArrayHasKey('courier_performance', $courier);
        $this->assertArrayHasKey('courier_rankings', $courier);
        $this->assertArrayHasKey('courier_reliability', $courier);
        $this->assertCount(2, $courier['courier_performance']);
    }

    /** @test */
    public function it_handles_empty_data_gracefully()
    {
        $this->mockCacheService->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $analytics = $this->analyticsService->getTenantAnalytics($this->tenant->id, [
            'start_date' => now()->subDays(30),
            'end_date' => now(),
        ]);

        $overview = $analytics['overview'];
        
        $this->assertEquals(0, $overview['total_shipments']);
        $this->assertEquals(0, $overview['delivered_shipments']);
        $this->assertEquals(0, $overview['success_rate']);
    }
}
