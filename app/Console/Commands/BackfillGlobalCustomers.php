<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Shipment;
use App\Services\GlobalCustomerService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class BackfillGlobalCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'global-customers:backfill 
                            {--chunk=1000 : Number of records to process per chunk}
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill global customer links for existing customers and shipments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }
        
        $this->info('Backfilling global customers...');
        $this->newLine();

        $svc = app(GlobalCustomerService::class);
        $chunkSize = (int) $this->option('chunk');

        // Disable model events and broadcasting during backfill for performance
        // No need for observers or websocket broadcasts during bulk operations
        // Use withoutEvents() wrapper for safer event handling
        Model::withoutEvents(function () use ($svc, $chunkSize, $dryRun) {
            // Step 1: Link customers to global customers
            $this->info('Step 1: Linking customers to global customers...');
            $customerCount = 0;
            $customerTotal = Customer::whereNull('global_customer_id')->count();
            
            $bar = $this->output->createProgressBar($customerTotal);
            $bar->start();
            
            // Use chunk() instead of chunkById() for UUIDs (lexicographic ordering is fine)
            Customer::whereNull('global_customer_id')
                ->orderBy('id')
                ->chunk($chunkSize, function ($chunk) use ($svc, $dryRun, &$customerCount, $bar) {
                    foreach ($chunk as $customer) {
                        try {
                            $email = trim((string) $customer->email);
                            $phone = trim((string) $customer->phone);
                            
                            if ($email === '' && $phone === '') {
                                // Skip customers with no identifiers
                            } else {
                                $gc = $svc->findOrCreateGlobalCustomer($email, $phone);
                                if (!$dryRun) {
                                    $customer->update(['global_customer_id' => $gc->id]);
                                }
                                $customerCount++;
                            }
                        } catch (\Exception $e) {
                            $this->newLine();
                            $this->warn("Failed to link customer {$customer->id}: {$e->getMessage()}");
                        } finally {
                            $bar->advance();
                        }
                    }
                });
            
            $bar->finish();
            $this->newLine(2);
            $this->info("✓ Linked {$customerCount} customers to global customers");

            // Step 2: Link shipments to global customers
            $this->info('Step 2: Linking shipments to global customers...');
            $shipmentCount = 0;
            $shipmentTotal = Shipment::whereNull('global_customer_id')
                ->whereNotNull('customer_id')
                ->count();
            
            $bar = $this->output->createProgressBar($shipmentTotal);
            $bar->start();
            
            // Use chunk() instead of chunkById() for UUIDs
            Shipment::whereNull('global_customer_id')
                ->whereNotNull('customer_id')
                ->orderBy('id')
                ->with('customer:id,global_customer_id')
                ->chunk($chunkSize, function ($chunk) use ($dryRun, &$shipmentCount, $bar) {
                    foreach ($chunk as $shipment) {
                        try {
                            if ($shipment->customer?->global_customer_id) {
                                if (!$dryRun) {
                                    $shipment->update(['global_customer_id' => $shipment->customer->global_customer_id]);
                                }
                                $shipmentCount++;
                            }
                        } catch (\Exception $e) {
                            $this->newLine();
                            $this->warn("Failed to link shipment {$shipment->id}: {$e->getMessage()}");
                        } finally {
                            $bar->advance();
                        }
                    }
                });
            
            $bar->finish();
            $this->newLine(2);
            $this->info("✓ Linked {$shipmentCount} shipments to global customers");
        });
        
        $this->newLine();
        if ($dryRun) {
            $this->info('✅ Dry run completed. Use without --dry-run to apply changes.');
        } else {
            $this->info('✅ Backfill completed successfully!');
        }
        
        return self::SUCCESS;
    }
}
