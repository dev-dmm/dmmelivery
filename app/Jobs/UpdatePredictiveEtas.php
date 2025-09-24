<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\PredictiveEtaService;
use App\Services\AlertSystemService;
use Illuminate\Support\Facades\Log;

class UpdatePredictiveEtas implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('🤖 Starting predictive ETA and alert system update');

        try {
            // Update predictive ETAs
            $predictiveEtaService = app(PredictiveEtaService::class);
            $updatedEtas = $predictiveEtaService->updateAllPredictiveEtas();
            
            Log::info("✅ Updated {$updatedEtas} predictive ETAs");

            // Check for alerts
            $alertSystemService = app(AlertSystemService::class);
            $alertsTriggered = $alertSystemService->checkAllShipments();
            
            Log::info("🚨 Triggered {$alertsTriggered} alerts");

        } catch (\Exception $e) {
            Log::error("❌ Error in predictive ETA and alert update: " . $e->getMessage());
            throw $e;
        }
    }
}
