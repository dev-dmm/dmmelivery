<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\FetchCourierStatuses;
use App\Models\Shipment;
use App\Models\Courier;

class TestCourierApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'courier:test-api {--shipment= : Test specific shipment tracking number}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test courier API integration and fetch status updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚚 Testing Courier API Integration');
        $this->newLine();

        $shipmentNumber = $this->option('shipment');
        
        if ($shipmentNumber) {
            $this->testSingleShipment($shipmentNumber);
        } else {
            $this->testAllCouriers();
        }
    }

    /**
     * Test a single shipment
     */
    private function testSingleShipment(string $trackingNumber): void
    {
        $shipment = Shipment::where('tracking_number', $trackingNumber)
            ->orWhere('courier_tracking_id', $trackingNumber)
            ->first();

        if (!$shipment) {
            $this->error("❌ Shipment not found: {$trackingNumber}");
            return;
        }

        $this->info("📦 Testing shipment: {$shipment->tracking_number}");
        $this->info("🏢 Courier: {$shipment->courier->name} ({$shipment->courier->code})");
        $this->info("📊 Current status: {$shipment->status}");
        $this->newLine();

        // Test the API call
        $this->withProgressBar([1], function () use ($shipment) {
            $job = new FetchCourierStatuses();
            
            // Use reflection to call private method for testing
            $reflection = new \ReflectionClass($job);
            $method = $reflection->getMethod('fetchShipmentStatus');
            $method->setAccessible(true);
            $method->invoke($job, $shipment->courier, $shipment);
        });

        $this->newLine(2);
        
        // Show updated status
        $shipment->refresh();
        $this->info("✅ Test completed!");
        $this->info("📊 Final status: {$shipment->status}");
        
        // Show recent status history
        $this->newLine();
        $this->info("📋 Recent status history:");
        $recentHistory = $shipment->statusHistory()->latest('happened_at')->limit(3)->get();
        
        foreach ($recentHistory as $history) {
            $this->line("  • {$history->happened_at->format('d/m/Y H:i')} - {$history->status} - {$history->description}");
        }
    }

    /**
     * Test all active couriers
     */
    private function testAllCouriers(): void
    {
        $this->info("🔄 Running full courier status fetch job");
        $this->newLine();

        // Show courier summary
        $couriers = Courier::where('is_active', true)
            ->whereNotNull('api_endpoint')
            ->whereNotNull('api_key')
            ->get();

        if ($couriers->isEmpty()) {
            $this->warn("⚠️ No active couriers with API configuration found!");
            $this->line("Make sure your couriers have api_endpoint and api_key configured.");
            return;
        }

        $this->info("📡 Found " . $couriers->count() . " active couriers with API integration:");
        foreach ($couriers as $courier) {
            $activeShipments = $courier->shipments()
                ->whereNotIn('status', ['delivered', 'cancelled', 'returned'])
                ->where('updated_at', '>=', now()->subDays(14))
                ->count();
                
            $this->line("  • {$courier->name} ({$courier->code}) - {$activeShipments} active shipments");
        }

        $this->newLine();
        
        if ($this->confirm('Do you want to proceed with the API calls?')) {
            $this->withProgressBar([1], function () {
                $job = new FetchCourierStatuses();
                $job->handle();
            });

            $this->newLine(2);
            $this->info("✅ Courier status fetch completed!");
            $this->info("📊 Check the logs for detailed results: storage/logs/laravel.log");
        } else {
            $this->info("❌ Test cancelled.");
        }
    }
}
