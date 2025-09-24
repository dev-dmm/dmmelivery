<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\UpdatePredictiveEtas;
use App\Services\AlertSystemService;

class UpdatePredictiveEtasCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'predictive-eta:update {--force : Force update even if recently updated}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update predictive ETAs and check for alerts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🤖 Starting predictive ETA and alert system update...');

        try {
            // Dispatch the job
            UpdatePredictiveEtas::dispatch();
            
            $this->info('✅ Predictive ETA update job dispatched successfully');
            
            // Also run alert system check immediately for testing
            if ($this->option('force')) {
                $this->info('🔍 Running immediate alert check...');
                $alertService = app(AlertSystemService::class);
                $alertsTriggered = $alertService->checkAllShipments();
                $this->info("🚨 Triggered {$alertsTriggered} alerts");
            }

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
