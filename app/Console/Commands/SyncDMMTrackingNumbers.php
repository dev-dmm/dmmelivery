<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DMMDeliveryService;
use App\Models\Shipment;

class SyncDMMTrackingNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dmm:sync-tracking-numbers 
                            {--force : Force sync even if tracking numbers already exist}
                            {--limit=100 : Limit the number of shipments to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync tracking numbers with DMM Delivery Bridge plugin data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting DMM Delivery tracking number sync...');

        $dmmService = app(DMMDeliveryService::class);
        
        // Get shipments to process
        $query = Shipment::with(['order']);
        
        if (!$this->option('force')) {
            // Only process shipments that don't have realistic tracking numbers
            $query->where(function($q) {
                $q->where('tracking_number', 'like', 'AB%')
                  ->orWhere('tracking_number', 'like', 'XY%')
                  ->orWhere('tracking_number', 'like', '??%');
            });
        }
        
        $shipments = $query->limit($this->option('limit'))->get();
        
        if ($shipments->isEmpty()) {
            $this->info('No shipments found to sync.');
            return;
        }

        $this->info("Found {$shipments->count()} shipments to process.");

        $bar = $this->output->createProgressBar($shipments->count());
        $bar->start();

        $successful = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($shipments as $shipment) {
            try {
                if (!$shipment->order) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $orderId = $shipment->order->id;
                
                // Check if order was sent to DMM Delivery
                if (!$dmmService->isOrderSentToDMM($orderId)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Update with real tracking data
                if ($dmmService->updateShipmentWithRealData($shipment)) {
                    $successful++;
                } else {
                    $failed++;
                }

            } catch (\Exception $e) {
                $this->error("Error processing shipment {$shipment->id}: " . $e->getMessage());
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Sync completed!");
        $this->info("✅ Successful: {$successful}");
        $this->info("❌ Failed: {$failed}");
        $this->info("⏭️ Skipped: {$skipped}");

        if ($successful > 0) {
            $this->info("🎉 Successfully synced {$successful} shipments with real DMM Delivery tracking numbers!");
        }
    }
}
