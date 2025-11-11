<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class CheckDeliveryScoreIntegrity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delivery-score:check-integrity 
                            {--tenant= : Check specific tenant only}
                            {--alert : Exit with error code if mismatches found}
                            {--fix : Auto-fix safe mismatches (only when journal=0 and score≠0 with baseline=0)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that journal delta sums match customer delivery_score changes per tenant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $shouldAlert = $this->option('alert');
        $shouldFix = $this->option('fix');
        
        $tenants = $tenantId 
            ? [Tenant::findOrFail($tenantId)]
            : Tenant::all();
        
        $hasMismatches = false;
        $fixedCount = 0;
        
        $this->info("Checking delivery score integrity for " . count($tenants) . " tenant(s)...\n");
        
        if ($shouldFix) {
            $this->warn("⚠️  --fix mode enabled: will attempt safe auto-repairs");
            $this->newLine();
        }
        
        foreach ($tenants as $tenant) {
            $result = $this->checkTenant($tenant);
            
            if ($result['mismatch']) {
                $hasMismatches = true;
                $this->error("❌ Tenant {$tenant->id} ({$tenant->name}): MISMATCH");
                $this->line("   Journal delta sum: {$result['journal_sum']}");
                $this->line("   Expected from baseline: {$result['expected_delta']}");
                $this->line("   Difference: {$result['difference']}");
                
                if (!empty($result['details'])) {
                    $this->line("   Affected customers: " . count($result['details']));
                    if ($this->getOutput()->isVerbose()) {
                        foreach ($result['details'] as $detail) {
                            $this->line("     - Customer {$detail['customer_id']}: journal={$detail['journal']}, score={$detail['score']}, diff={$detail['diff']}");
                        }
                    }
                    
                    // Attempt safe fixes
                    if ($shouldFix) {
                        $fixed = $this->attemptFixes($tenant, $result['details']);
                        $fixedCount += $fixed;
                        if ($fixed > 0) {
                            $this->info("   ✅ Fixed {$fixed} safe mismatch(es)");
                        }
                    }
                }
            } else {
                $this->info("✅ Tenant {$tenant->id} ({$tenant->name}): OK");
                if ($this->getOutput()->isVerbose()) {
                    $this->line("   Journal delta sum: {$result['journal_sum']}");
                    $this->line("   All customers match");
                }
            }
        }
        
        if ($hasMismatches) {
            $this->newLine();
            if ($shouldFix && $fixedCount > 0) {
                $this->info("🔧 Fixed {$fixedCount} safe mismatch(es) automatically");
                $this->newLine();
            }
            
            $this->error("⚠️  Integrity check found mismatches!");
            $this->line("This may indicate:");
            $this->line("  - Manual score adjustments");
            $this->line("  - Missing journal entries");
            $this->line("  - Data corruption");
            
            if ($shouldAlert) {
                return Command::FAILURE;
            }
        } else {
            $this->newLine();
            $this->info("✅ All tenants passed integrity check!");
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Attempt to fix safe mismatches
     * Only fixes cases where journal=0 but score≠0 (assuming baseline=0)
     */
    private function attemptFixes(Tenant $tenant, array $mismatches): int
    {
        $fixed = 0;
        
        foreach ($mismatches as $mismatch) {
            // Safe fix: journal=0 but score≠0 (reset score to 0, assuming baseline)
            // This handles cases where scores were manually adjusted or corrupted
            if ($mismatch['journal'] == 0 && $mismatch['score'] != 0) {
                try {
                    DB::table('customers')
                        ->where('id', $mismatch['customer_id'])
                        ->where('tenant_id', $tenant->id)
                        ->update(['delivery_score' => 0]);
                    
                    $fixed++;
                } catch (\Throwable $e) {
                    // Skip if update fails
                }
            }
            // Note: We don't auto-fix cases where journal≠0 but score doesn't match
            // as this could indicate legitimate manual adjustments
        }
        
        return $fixed;
    }
    
    /**
     * Check integrity for a single tenant
     */
    private function checkTenant(Tenant $tenant): array
    {
        // Get journal delta sum per tenant
        $journalSum = DB::table('delivery_score_journal')
            ->join('shipments', 'shipments.id', '=', 'delivery_score_journal.shipment_id')
            ->where('shipments.tenant_id', $tenant->id)
            ->sum('delivery_score_journal.delta') ?? 0;
        
        // Get per-customer breakdown for detailed reporting
        $customerDetails = DB::table('delivery_score_journal')
            ->join('shipments', 'shipments.id', '=', 'delivery_score_journal.shipment_id')
            ->join('customers', 'customers.id', '=', 'delivery_score_journal.customer_id')
            ->where('shipments.tenant_id', $tenant->id)
            ->select(
                'delivery_score_journal.customer_id',
                DB::raw('SUM(delivery_score_journal.delta) as journal_delta'),
                'customers.delivery_score'
            )
            ->groupBy('delivery_score_journal.customer_id', 'customers.delivery_score')
            ->get();
        
        $mismatches = [];
        $totalExpected = 0;
        
        foreach ($customerDetails as $detail) {
            // Note: This assumes baseline score was 0. In production, you might want to track baseline.
            $expectedDelta = $detail->journal_delta;
            $actualScore = $detail->delivery_score;
            $difference = $actualScore - $expectedDelta;
            
            if (abs($difference) > 0) {
                $mismatches[] = [
                    'customer_id' => $detail->customer_id,
                    'journal' => $expectedDelta,
                    'score' => $actualScore,
                    'diff' => $difference,
                ];
            }
            
            $totalExpected += $expectedDelta;
        }
        
        return [
            'mismatch' => !empty($mismatches),
            'journal_sum' => $journalSum,
            'expected_delta' => $totalExpected,
            'difference' => $journalSum - $totalExpected,
            'details' => $mismatches,
        ];
    }
}
