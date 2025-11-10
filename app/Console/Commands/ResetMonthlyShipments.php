<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class ResetMonthlyShipments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:reset-monthly-shipments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset monthly shipment counters for all tenants based on billing cycle';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking monthly shipment counters…');

        $now = now()->timezone(config('app.timezone', 'UTC'));
        $todayDay = (int) $now->day;
        $monthLastDay = (int) $now->copy()->endOfMonth()->day;

        $tenants = Tenant::query()
            ->where('is_active', true)
            ->get(['id', 'billing_cycle_start', 'current_month_shipments']);

        $resets = 0;

        foreach ($tenants as $tenant) {
            // If tenant has a billing cycle day, use it; else default to 1
            $cycleDay = $tenant->billing_cycle_start?->day ?? 1;

            // If the cycle day is beyond this month's last day, collapse to last day
            $effectiveDay = min($cycleDay, $monthLastDay);

            if ($todayDay !== $effectiveDay) {
                continue; // not today
            }

            $tenant->update(['current_month_shipments' => 0]);
            $resets++;

            Log::info('Monthly shipment counter reset', [
                'tenant_id' => $tenant->id,
                'configured_cycle_day' => $cycleDay,
                'effective_cycle_day' => $effectiveDay,
                'month' => $now->format('Y-m'),
            ]);
        }

        $this->info("Reset shipment counters for {$resets} tenant(s).");

        return self::SUCCESS;
    }
}
