<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Shipment;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Courier;
use App\Models\Tenant;
use App\Services\DMMDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DMMDeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_uses_realistic_tracking_numbers()
    {
        // Create test data
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $courier = Courier::factory()->create(['tenant_id' => $tenant->id, 'code' => 'ACS']);
        $order = Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        
        $shipment = Shipment::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
            'order_id' => $order->id,
        ]);

        // Check that tracking number follows realistic pattern
        $this->assertNotEmpty($shipment->tracking_number);
        $this->assertStringStartsWith('ACS', $shipment->tracking_number);
        $this->assertGreaterThan(10, strlen($shipment->tracking_number));
    }

    public function test_dmm_service_handles_missing_wordpress_gracefully()
    {
        $dmmService = app(DMMDeliveryService::class);
        
        // Should not throw exception when WordPress functions don't exist
        $result = $dmmService->getRealTrackingNumber('test-order-id');
        $this->assertNull($result);
    }

    public function test_sync_command_works_without_errors()
    {
        // Create test shipment
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $courier = Courier::factory()->create(['tenant_id' => $tenant->id]);
        $order = Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        
        $shipment = Shipment::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
            'order_id' => $order->id,
        ]);

        // Run the sync command
        $this->artisan('dmm:sync-tracking-numbers', ['--limit' => 10])
             ->assertExitCode(0);
    }
}
