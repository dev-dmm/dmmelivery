<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class CheckDeliveryScoreIntegrityCommandTest extends TestCase
{
    protected Tenant $tenant;
    protected Customer $customer;
    protected Courier $courier;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'delivery_score' => 1, // Mismatch: score exists but no journal entry
        ]);
        $this->courier = Courier::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test that integrity check detects mismatches
     */
    public function test_integrity_check_detects_mismatches(): void
    {
        // Customer has score but no journal entries (mismatch)
        $this->assertEquals(1, $this->customer->delivery_score);
        $this->assertEquals(0, DB::table('delivery_score_journal')
            ->where('customer_id', $this->customer->id)
            ->count());

        // Run integrity check
        Artisan::call('delivery-score:check-integrity', [
            '--tenant' => $this->tenant->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('MISMATCH', $output);
    }

    /**
     * Test that --fix flag auto-repairs safe mismatches
     */
    public function test_fix_flag_auto_repairs_safe_mismatches(): void
    {
        // Customer has score=1 but no journal entries (safe to fix: journal=0, score≠0)
        $this->assertEquals(1, $this->customer->delivery_score);
        $this->assertEquals(0, DB::table('delivery_score_journal')
            ->where('customer_id', $this->customer->id)
            ->count());

        // Run integrity check with --fix
        Artisan::call('delivery-score:check-integrity', [
            '--tenant' => $this->tenant->id,
            '--fix' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('Fixed', $output);

        // Verify score was reset to 0
        $this->customer->refresh();
        $this->assertEquals(0, $this->customer->delivery_score);
    }

    /**
     * Test that integrity check passes when data is consistent
     */
    public function test_integrity_check_passes_when_consistent(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
            'scored_at' => null,
        ]);

        // Reset customer score to 0 for clean test
        $this->customer->update(['delivery_score' => 0]);

        // Score the shipment (creates journal entry)
        $shipment->updateCustomerDeliveryScore('in_transit', 'delivered');

        // Verify consistency
        $this->customer->refresh();
        $journalSum = DB::table('delivery_score_journal')
            ->join('shipments', 'shipments.id', '=', 'delivery_score_journal.shipment_id')
            ->where('shipments.tenant_id', $this->tenant->id)
            ->sum('delivery_score_journal.delta') ?? 0;

        $this->assertEquals($this->customer->delivery_score, $journalSum);

        // Run integrity check
        Artisan::call('delivery-score:check-integrity', [
            '--tenant' => $this->tenant->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('OK', $output);
        $this->assertStringNotContainsString('MISMATCH', $output);
    }

    /**
     * Test that --alert flag exits with error code on mismatches
     */
    public function test_alert_flag_exits_with_error_on_mismatch(): void
    {
        // Create mismatch
        $this->customer->update(['delivery_score' => 5]);

        // Run with --alert flag
        $exitCode = Artisan::call('delivery-score:check-integrity', [
            '--tenant' => $this->tenant->id,
            '--alert' => true,
        ]);

        $this->assertEquals(1, $exitCode); // Command::FAILURE
    }
}

