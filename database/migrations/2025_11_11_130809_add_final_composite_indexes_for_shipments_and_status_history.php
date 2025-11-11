<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds final composite indexes for optimal query performance:
     * - shipments (tenant_id, status, created_at desc) - for paginated list queries
     * - shipment_status_history (shipment_id, happened_at desc) - for status history queries
     * - shipments (tracking_number) UNIQUE - ensures DB-level uniqueness
     */
    public function up(): void
    {
        // Composite index for shipments: tenant_id + status + created_at (desc order)
        // This optimizes the most common query pattern: list shipments by tenant, filtered by status, ordered by date
        Schema::table('shipments', function (Blueprint $table) {
            try {
                // Note: MySQL doesn't support DESC in index definition, but PostgreSQL does
                // For MySQL, the index will be created without DESC (still efficient for ORDER BY ... DESC)
                if (config('database.default') === 'pgsql') {
                    // PostgreSQL supports DESC in index
                    DB::statement('CREATE INDEX IF NOT EXISTS shipments_tenant_status_created_desc_idx ON shipments (tenant_id, status, created_at DESC)');
                } else {
                    // MySQL/SQLite: create index without DESC (still efficient)
                    $table->index(['tenant_id', 'status', 'created_at'], 'shipments_tenant_status_created_desc_idx');
                }
            } catch (\Exception $e) {
                // Index might already exist, skip
            }
            
            // Ensure tracking_number has UNIQUE index (DB-level constraint)
            // This complements the validation rule and prevents race conditions
            try {
                // Check if unique index already exists
                $indexes = DB::select("SHOW INDEX FROM shipments WHERE Key_name = 'shipments_tracking_number_unique'");
                if (empty($indexes)) {
                    $table->unique('tracking_number', 'shipments_tracking_number_unique');
                }
            } catch (\Exception $e) {
                // Index might already exist or DB doesn't support SHOW INDEX, try to create anyway
                try {
                    $table->unique('tracking_number', 'shipments_tracking_number_unique');
                } catch (\Exception $e2) {
                    // Skip if unique constraint already exists
                }
            }
        });

        // Composite index for shipment_status_history: shipment_id + happened_at (desc order)
        // This optimizes status history queries (already ordered desc by default in relationship)
        Schema::table('shipment_status_histories', function (Blueprint $table) {
            try {
                if (config('database.default') === 'pgsql') {
                    // PostgreSQL supports DESC in index
                    DB::statement('CREATE INDEX IF NOT EXISTS ssh_shipment_happened_desc_idx ON shipment_status_histories (shipment_id, happened_at DESC)');
                } else {
                    // MySQL/SQLite: create index without DESC (still efficient for ORDER BY ... DESC)
                    $table->index(['shipment_id', 'happened_at'], 'ssh_shipment_happened_desc_idx');
                }
            } catch (\Exception $e) {
                // Index might already exist (e.g., idx_status_history_shipment_time), skip
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_status_histories', function (Blueprint $table) {
            try {
                $table->dropIndex('ssh_shipment_happened_desc_idx');
            } catch (\Exception $e) {
                // Index might not exist, skip
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            try {
                $table->dropIndex('shipments_tenant_status_created_desc_idx');
            } catch (\Exception $e) {
                // Index might not exist, skip
            }
            
            try {
                $table->dropUnique('shipments_tracking_number_unique');
            } catch (\Exception $e) {
                // Unique constraint might not exist, skip
            }
        });
    }
};
