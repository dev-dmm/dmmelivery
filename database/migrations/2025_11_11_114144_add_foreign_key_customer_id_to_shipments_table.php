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
     * Adds foreign key constraint for customer_id to ensure referential integrity.
     * Uses SET NULL on delete to preserve shipment history even if customer is deleted.
     * Also adds index for FK performance (many DBs don't auto-index FKs).
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        // Check existence separately (best practice: avoid PDO inside Blueprint quirks)
        $indexExists = false;
        $fkExists = false;
        
        if ($driver === 'mysql') {
            // Check index
            $indexResult = DB::selectOne("
                SELECT COUNT(*) as c FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'shipments'
                  AND INDEX_NAME = 'shipments_customer_id_idx'
            ");
            $indexExists = ($indexResult->c ?? 0) > 0;
            
            // Check FK
            $fkResult = DB::selectOne("
                SELECT COUNT(*) as c FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'shipments'
                  AND CONSTRAINT_NAME = 'shipments_customer_id_fk'
            ");
            $fkExists = ($fkResult->c ?? 0) > 0;
        } elseif ($driver === 'pgsql') {
            // Check index
            $indexResult = DB::selectOne("
                SELECT COUNT(*) as c FROM pg_indexes
                WHERE tablename = 'shipments'
                  AND indexname = 'shipments_customer_id_idx'
                  AND schemaname = current_schema()
            ");
            $indexExists = ($indexResult->c ?? 0) > 0;
            
            // Check FK
            $fkResult = DB::selectOne("
                SELECT COUNT(*) as c FROM information_schema.table_constraints
                WHERE table_name = 'shipments'
                  AND constraint_name = 'shipments_customer_id_fk'
                  AND constraint_schema = current_schema()
            ");
            $fkExists = ($fkResult->c ?? 0) > 0;
        }
        
        // Add index first (if not exists) - many DBs don't auto-index FKs
        if (!$indexExists) {
            Schema::table('shipments', function (Blueprint $table) {
                try {
                    $table->index('customer_id', 'shipments_customer_id_idx');
                } catch (\Throwable $e) {
                    // Index might have been created concurrently, skip
                }
            });
        }
        
        // Add FK with explicit name for easier monitoring/dropping
        if (!$fkExists) {
            Schema::table('shipments', function (Blueprint $table) {
                try {
                    $table->foreign('customer_id', 'shipments_customer_id_fk')
                        ->references('id')
                        ->on('customers')
                        ->onDelete('set null');
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
        Schema::table('shipments', function (Blueprint $table) {
            try {
                $table->dropForeign('shipments_customer_id_fk');
            } catch (\Throwable $e) {
                // Foreign key might not exist, skip
            }
            
            try {
                $table->dropIndex('shipments_customer_id_idx');
            } catch (\Throwable $e) {
                // Index might not exist, skip
            }
        });
    }
};
