<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds composite index for efficient reporting queries like "scored shipments this month per tenant".
     * Index on (tenant_id, scored_at) allows fast filtering by tenant and date range.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            try {
                $table->index(['tenant_id', 'scored_at'], 'shipments_tenant_scored_idx');
            } catch (\Throwable $e) {
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
                $table->dropIndex('shipments_tenant_scored_idx');
            } catch (\Throwable $e) {
                // Index might not exist, skip
            }
        });
    }
};
