<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test environment
        $this->artisan('migrate', ['--database' => 'testing']);
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Create a test user with tenant
     */
    protected function createUserWithTenant(): array
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        
        return [$user, $tenant];
    }

    /**
     * Create a complete shipment setup
     */
    protected function createShipmentSetup(): array
    {
        [$user, $tenant] = $this->createUserWithTenant();
        
        $customer = \App\Models\Customer::factory()->create(['tenant_id' => $tenant->id]);
        $courier = \App\Models\Courier::factory()->create(['tenant_id' => $tenant->id]);
        $order = \App\Models\Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
        ]);
        
        return [$user, $tenant, $customer, $courier, $order];
    }

    /**
     * Assert JSON structure with nested arrays
     */
    protected function assertJsonStructureRecursive(array $structure, array $data, string $prefix = ''): void
    {
        foreach ($structure as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value)) {
                if (isset($data[$key]) && is_array($data[$key])) {
                    $this->assertJsonStructureRecursive($value, $data[$key], $fullKey);
                } else {
                    $this->fail("Expected array structure at '{$fullKey}', but got: " . gettype($data[$key] ?? 'null'));
                }
            } else {
                $this->assertArrayHasKey($key, $data, "Missing key '{$fullKey}' in response");
            }
        }
    }

    /**
     * Assert API response structure
     */
    protected function assertApiResponse(array $response, int $expectedStatus = 200): void
    {
        $this->assertEquals($expectedStatus, $response['status'] ?? 200);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('data', $response);
    }

    /**
     * Assert error response structure
     */
    protected function assertErrorResponse(array $response, int $expectedStatus = 400): void
    {
        $this->assertEquals($expectedStatus, $response['status'] ?? 400);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertFalse($response['success']);
    }

    /**
     * Create mock for external services
     */
    protected function mockExternalService(string $service, array $methods = []): \Mockery\MockInterface
    {
        $mock = \Mockery::mock($service);
        
        foreach ($methods as $method => $returnValue) {
            $mock->shouldReceive($method)->andReturn($returnValue);
        }
        
        $this->app->instance($service, $mock);
        
        return $mock;
    }

    /**
     * Assert database has specific structure
     */
    protected function assertDatabaseStructure(string $table, array $expectedColumns): void
    {
        $columns = \Schema::getColumnListing($table);
        
        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column '{$column}' not found in table '{$table}'");
        }
    }

    /**
     * Assert cache has key
     */
    protected function assertCacheHas(string $key): void
    {
        $this->assertTrue(\Cache::has($key), "Cache key '{$key}' not found");
    }

    /**
     * Assert cache does not have key
     */
    protected function assertCacheMissing(string $key): void
    {
        $this->assertFalse(\Cache::has($key), "Cache key '{$key}' should not exist");
    }

    /**
     * Create test data with relationships
     */
    protected function createTestDataWithRelations(): array
    {
        [$user, $tenant, $customer, $courier, $order] = $this->createShipmentSetup();
        
        $shipment = \App\Models\Shipment::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
        ]);
        
        $predictiveEta = \App\Models\PredictiveEta::factory()->create([
            'shipment_id' => $shipment->id,
            'tenant_id' => $tenant->id,
        ]);
        
        $alert = \App\Models\Alert::factory()->create([
            'tenant_id' => $tenant->id,
            'shipment_id' => $shipment->id,
        ]);
        
        return [
            'user' => $user,
            'tenant' => $tenant,
            'customer' => $customer,
            'courier' => $courier,
            'order' => $order,
            'shipment' => $shipment,
            'predictiveEta' => $predictiveEta,
            'alert' => $alert,
        ];
    }
}