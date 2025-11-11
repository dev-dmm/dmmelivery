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
     * This migration ensures explicit names for indexes and FKs in the journal table.
     * The original migration already has these, but this ensures they exist with proper names
     * for easier monitoring and maintenance.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        // Check if unique index exists
        $uniqueExists = false;
        $customerIdxExists = false;
        $shipmentFkExists = false;
        $customerFkExists = false;
        
        if ($driver === 'mysql') {
            // Check unique index
            $result = DB::selectOne("
                SELECT COUNT(*) as c FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'delivery_score_journal'
                  AND INDEX_NAME = 'delivery_score_journal_shipment_uidx'
                  AND NON_UNIQUE = 0
            ");
            $uniqueExists = ($result->c ?? 0) > 0;
            
            // Check regular indexes (no shipment_idx - unique covers it)
            $result = DB::selectOne("
                SELECT COUNT(*) as c FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'delivery_score_journal'
                  AND INDEX_NAME = 'dsj_customer_idx'
            ");
            $customerIdxExists = ($result->c ?? 0) > 0;
            
            // Check FKs
            $result = DB::selectOne("
                SELECT COUNT(*) as c FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'delivery_score_journal'
                  AND CONSTRAINT_NAME = 'dsj_shipment_fk'
            ");
            $shipmentFkExists = ($result->c ?? 0) > 0;
            
            $result = DB::selectOne("
                SELECT COUNT(*) as c FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'delivery_score_journal'
                  AND CONSTRAINT_NAME = 'dsj_customer_fk'
            ");
            $customerFkExists = ($result->c ?? 0) > 0;
        } elseif ($driver === 'pgsql') {
            // Check unique index
            $result = DB::selectOne("
                SELECT COUNT(*) as c FROM pg_indexes
                WHERE tablename = 'delivery_score_journal'
                  AND indexname = 'delivery_score_journal_shipment_uidx'
                  AND schemaname = current_schema()
            ");
            $uniqueExists = ($result->c ?? 0) > 0;
            
            // Check regular indexes (no shipment_idx - unique covers it)
            $result = DB::selectOne("
                SELECT COUNT(*) as c FROM pg_indexes
                WHERE tablename = 'delivery_score_journal'
                  AND indexname = 'dsj_customer_idx'
                  AND schemaname = current_schema()
            ");
            $customerIdxExists = ($result->c ?? 0) > 0;
            
            // Check FKs
            $result = DB::selectOne("
                SELECT COUNT(*) as c FROM information_schema.table_constraints
                WHERE table_name = 'delivery_score_journal'
                  AND constraint_name = 'dsj_shipment_fk'
                  AND constraint_schema = current_schema()
            ");
            $shipmentFkExists = ($result->c ?? 0) > 0;
            
            $result = DB::selectOne("
                SELECT COUNT(*) as c FROM information_schema.table_constraints
                WHERE table_name = 'delivery_score_journal'
                  AND constraint_name = 'dsj_customer_fk'
                  AND constraint_schema = current_schema()
            ");
            $customerFkExists = ($result->c ?? 0) > 0;
        }
        
        // Add unique index if missing (DB-level protection: 1 row per shipment)
        if (!$uniqueExists) {
            try {
                DB::statement("
                    CREATE UNIQUE INDEX delivery_score_journal_shipment_uidx
                    ON delivery_score_journal (shipment_id)
                ");
            } catch (\Throwable $e) {
                // Index might have been created concurrently, skip
            }
        }
        
        // Add indexes if missing (no shipment_idx - unique index covers it)
        if (!$customerIdxExists) {
            Schema::table('delivery_score_journal', function (Blueprint $table) {
                try {
                    $table->index('customer_id', 'dsj_customer_idx');
                } catch (\Throwable $e) {
                    // Index might have been created concurrently, skip
                }
            });
        }
        
        // Add FKs with explicit names if missing
        if (!$shipmentFkExists) {
            Schema::table('delivery_score_journal', function (Blueprint $table) {
                try {
                    $table->foreign('shipment_id', 'dsj_shipment_fk')
                        ->references('id')
                        ->on('shipments')
                        ->onDelete('cascade');
                } catch (\Throwable $e) {
                    // FK might have been created concurrently, skip
                }
            });
        }
        
        if (!$customerFkExists) {
            Schema::table('delivery_score_journal', function (Blueprint $table) {
                try {
                    $table->foreign('customer_id', 'dsj_customer_fk')
                        ->references('id')
                        ->on('customers')
                        ->onDelete('cascade');
                } catch (\Throwable $e) {
                    // FK might have been created concurrently, skip
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_score_journal', function (Blueprint $table) {
            try {
                $table->dropForeign('dsj_customer_fk');
            } catch (\Throwable $e) {
                // FK might not exist, skip
            }
            
            try {
                $table->dropForeign('dsj_shipment_fk');
            } catch (\Throwable $e) {
                // FK might not exist, skip
            }
            
            try {
                $table->dropIndex('dsj_customer_idx');
            } catch (\Throwable $e) {
                // Index might not exist, skip
            }
        });
        
        // Drop unique index (per-driver syntax)
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // MySQL syntax: DROP INDEX <name> ON <table>
            try {
                DB::statement("DROP INDEX delivery_score_journal_shipment_uidx ON delivery_score_journal");
            } catch (\Throwable $e) {
                // Index might not exist, skip
            }
        } elseif ($driver === 'pgsql') {
            // PostgreSQL syntax: DROP INDEX IF EXISTS <name>
            try {
                DB::statement("DROP INDEX IF EXISTS delivery_score_journal_shipment_uidx");
            } catch (\Throwable $e) {
                // Index might not exist, skip
            }
        }
    }
};
