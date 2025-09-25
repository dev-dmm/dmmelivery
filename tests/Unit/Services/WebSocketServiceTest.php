<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\WebSocketService;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Alert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;
use Pusher\Pusher;

class WebSocketServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private WebSocketService $webSocketService;
    private Tenant $tenant;
    private Shipment $shipment;
    private $mockPusher;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->courier = Courier::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->order = Order::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        
        $this->shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
        ]);

        // Mock Pusher
        $this->mockPusher = Mockery::mock(Pusher::class);
        $this->app->instance(Pusher::class, $this->mockPusher);
        
        $this->webSocketService = new WebSocketService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_broadcast_shipment_update()
    {
        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->with(
                "dmmelivery_tenant_{$this->tenant->id}",
                'shipment.updated',
                Mockery::type('array')
            )
            ->andReturn(true);

        $this->webSocketService->broadcastShipmentUpdate($this->shipment, [
            'old_status' => 'pending',
            'new_status' => 'in_transit',
        ]);

        $this->assertTrue(true); // If we get here, the method executed without errors
    }

    /** @test */
    public function it_can_broadcast_new_shipment()
    {
        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->with(
                "dmmelivery_tenant_{$this->tenant->id}",
                'shipment.created',
                Mockery::type('array')
            )
            ->andReturn(true);

        $this->webSocketService->broadcastNewShipment($this->shipment);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_broadcast_shipment_delivered()
    {
        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->with(
                "dmmelivery_tenant_{$this->tenant->id}",
                'shipment.delivered',
                Mockery::type('array')
            )
            ->andReturn(true);

        $this->webSocketService->broadcastShipmentDelivered($this->shipment);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_broadcast_alert()
    {
        $alert = Alert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'shipment_id' => $this->shipment->id,
        ]);

        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->with(
                "dmmelivery_tenant_{$this->tenant->id}",
                'alert.triggered',
                Mockery::type('array')
            )
            ->andReturn(true);

        $this->webSocketService->broadcastAlert($alert);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_broadcast_dashboard_update()
    {
        $stats = [
            'total_shipments' => 100,
            'delivered_shipments' => 95,
            'success_rate' => 95.0,
        ];

        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->with(
                "dmmelivery_tenant_{$this->tenant->id}",
                'dashboard.updated',
                $stats
            )
            ->andReturn(true);

        $this->webSocketService->broadcastDashboardUpdate($this->tenant->id, $stats);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_broadcast_courier_update()
    {
        $courierData = [
            'courier_id' => $this->courier->id,
            'name' => $this->courier->name,
            'status' => 'active',
        ];

        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->with(
                "dmmelivery_tenant_{$this->tenant->id}",
                'courier.updated',
                $courierData
            )
            ->andReturn(true);

        $this->webSocketService->broadcastCourierUpdate($this->tenant->id, $courierData);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_broadcast_to_specific_user()
    {
        $user = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id]);
        $data = ['message' => 'Test notification'];

        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->with(
                "dmmelivery_user_{$user->id}",
                'user.update',
                $data
            )
            ->andReturn(true);

        $this->webSocketService->broadcastToUser($user->id, 'user.update', $data);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_broadcast_system_notification()
    {
        $message = 'System maintenance scheduled';
        $type = 'info';

        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->with(
                "dmmelivery_tenant_{$this->tenant->id}",
                'system.notification',
                Mockery::type('array')
            )
            ->andReturn(true);

        $this->webSocketService->broadcastSystemNotification($this->tenant->id, $message, $type);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_get_channel_auth()
    {
        $channel = "dmmelivery_tenant_{$this->tenant->id}";
        $socketId = '123.456';

        $this->mockPusher->shouldReceive('socket_auth')
            ->once()
            ->with($channel, $socketId)
            ->andReturn('auth_string');

        $result = $this->webSocketService->getChannelAuth($channel, $socketId);

        $this->assertEquals(['auth' => 'auth_string'], $result);
    }

    /** @test */
    public function it_can_test_connection()
    {
        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->with('test-channel', 'test-event', ['message' => 'test'])
            ->andReturn(true);

        $result = $this->webSocketService->testConnection();

        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_pusher_exceptions_gracefully()
    {
        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->andThrow(new \Exception('Pusher error'));

        // Should not throw exception
        $this->webSocketService->broadcastShipmentUpdate($this->shipment);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_auth_exceptions_gracefully()
    {
        $this->mockPusher->shouldReceive('socket_auth')
            ->once()
            ->andThrow(new \Exception('Auth error'));

        $result = $this->webSocketService->getChannelAuth('test-channel', 'socket-id');

        $this->assertEquals(['error' => 'Authentication failed'], $result);
    }

    /** @test */
    public function it_handles_connection_test_exceptions()
    {
        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->andThrow(new \Exception('Connection error'));

        $result = $this->webSocketService->testConnection();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_gets_tenant_channel_correctly()
    {
        $reflection = new \ReflectionClass($this->webSocketService);
        $method = $reflection->getMethod('getTenantChannel');
        $method->setAccessible(true);

        $channel = $method->invoke($this->webSocketService, $this->tenant->id);

        $this->assertEquals("dmmelivery_tenant_{$this->tenant->id}", $channel);
    }

    /** @test */
    public function it_gets_user_channel_correctly()
    {
        $userId = '123';
        $channel = $this->webSocketService->getUserChannel($userId);

        $this->assertEquals("dmmelivery_user_{$userId}", $channel);
    }

    /** @test */
    public function it_includes_correct_data_in_shipment_update()
    {
        $updateData = ['old_status' => 'pending', 'new_status' => 'in_transit'];

        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->with(
                Mockery::type('string'),
                'shipment.updated',
                Mockery::on(function ($data) {
                    return isset($data['shipment_id']) &&
                           isset($data['tracking_number']) &&
                           isset($data['status']) &&
                           isset($data['update_data']);
                })
            )
            ->andReturn(true);

        $this->webSocketService->broadcastShipmentUpdate($this->shipment, $updateData);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_includes_correct_data_in_new_shipment()
    {
        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->with(
                Mockery::type('string'),
                'shipment.created',
                Mockery::on(function ($data) {
                    return isset($data['shipment_id']) &&
                           isset($data['tracking_number']) &&
                           isset($data['status']) &&
                           isset($data['created_at']);
                })
            )
            ->andReturn(true);

        $this->webSocketService->broadcastNewShipment($this->shipment);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_includes_correct_data_in_alert()
    {
        $alert = Alert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'shipment_id' => $this->shipment->id,
        ]);

        $this->mockPusher->shouldReceive('trigger')
            ->once()
            ->with(
                Mockery::type('string'),
                'alert.triggered',
                Mockery::on(function ($data) {
                    return isset($data['alert_id']) &&
                           isset($data['title']) &&
                           isset($data['description']) &&
                           isset($data['alert_type']);
                })
            )
            ->andReturn(true);

        $this->webSocketService->broadcastAlert($alert);

        $this->assertTrue(true);
    }
}
