<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Index for reconciliation queries and statistics (tenant + status + created_at)
            try {
                $table->index(['tenant_id', 'status', 'created_at'], 'shipments_tenant_status_created_idx');
            } catch (\Exception $e) {
                // Index might already exist, skip
            }
            
            // Index for customer status lookups
            try {
                $table->index(['customer_id', 'status'], 'shipments_customer_status_idx');
            } catch (\Exception $e) {
                // Index might already exist, skip
            }
            
            // Index for scored_at (useful for reports like "scored this month")
            try {
                $table->index('scored_at', 'shipments_scored_at_idx');
            } catch (\Exception $e) {
                // Index might already exist, skip
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            try {
                $table->dropIndex('shipments_tenant_status_created_idx');
            } catch (\Exception $e) {
                // Index might not exist, skip
            }
            
            try {
                $table->dropIndex('shipments_customer_status_idx');
            } catch (\Exception $e) {
                // Index might not exist, skip
            }
            
            try {
                $table->dropIndex('shipments_scored_at_idx');
            } catch (\Exception $e) {
                // Index might not exist, skip
            }
        });
    }
};
