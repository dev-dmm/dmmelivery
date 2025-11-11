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
     * Adds tenant_id to journal table for efficient reporting without joins.
     * This materializes tenant_id to avoid JOIN shipments in aggregate queries.
     * Optional: only run if you frequently query journal per tenant without shipment context.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        // Check if column already exists
        $columnExists = false;
        if ($driver === 'mysql') {
            $result = DB::selectOne("
                SELECT COUNT(*) as c FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'delivery_score_journal'
                  AND COLUMN_NAME = 'tenant_id'
            ");
            $columnExists = ($result->c ?? 0) > 0;
        } elseif ($driver === 'pgsql') {
            $result = DB::selectOne("
                SELECT COUNT(*) as c FROM information_schema.columns
                WHERE table_name = 'delivery_score_journal'
                  AND column_name = 'tenant_id'
                  AND table_schema = current_schema()
            ");
            $columnExists = ($result->c ?? 0) > 0;
        }
        
        if ($columnExists) {
            return; // Column already exists
        }
        
        // Add tenant_id column
        Schema::table('delivery_score_journal', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('customer_id');
        });
        
        // Backfill tenant_id from shipments
        try {
            DB::statement("
                UPDATE delivery_score_journal dj
                INNER JOIN shipments s ON s.id = dj.shipment_id
                SET dj.tenant_id = s.tenant_id
            ");
        } catch (\Throwable $e) {
            // Might fail if using PostgreSQL - use different syntax
            if ($driver === 'pgsql') {
                try {
                    DB::statement("
                        UPDATE delivery_score_journal dj
                        SET tenant_id = s.tenant_id
                        FROM shipments s
                        WHERE s.id = dj.shipment_id
                    ");
                } catch (\Throwable $e2) {
                    // Skip if backfill fails
                }
            }
        }
        
        // Make tenant_id NOT NULL after backfill
        if ($driver === 'mysql') {
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    MODIFY COLUMN tenant_id CHAR(36) NOT NULL
                ");
            } catch (\Throwable $e) {
                // Might fail if NULLs exist, skip
            }
        } elseif ($driver === 'pgsql') {
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ALTER COLUMN tenant_id SET NOT NULL
                ");
            } catch (\Throwable $e) {
                // Might fail if NULLs exist, skip
            }
        }
        
        // Add index for reporting queries
        Schema::table('delivery_score_journal', function (Blueprint $table) {
            try {
                $table->index(['tenant_id', 'created_at'], 'journal_tenant_created_idx');
            } catch (\Throwable $e) {
                // Index might already exist, skip
            }
        });
        
        // Add FK constraint
        Schema::table('delivery_score_journal', function (Blueprint $table) {
            try {
                $table->foreign('tenant_id', 'dsj_tenant_fk')
                    ->references('id')
                    ->on('tenants')
                    ->onDelete('cascade');
            } catch (\Throwable $e) {
                // FK might already exist, skip
            }
        });
        
        // Optional: Create trigger to keep tenant_id in sync (PostgreSQL)
        // Handles both INSERT and UPDATE (in case shipment_id changes, though rare)
        if ($driver === 'pgsql') {
            try {
                // Drop triggers first for clean idempotency
                DB::statement("DROP TRIGGER IF EXISTS journal_tenant_id_sync_insert ON delivery_score_journal");
                DB::statement("DROP TRIGGER IF EXISTS journal_tenant_id_sync_update ON delivery_score_journal");
                
                // Create or replace function
                DB::statement("
                    CREATE OR REPLACE FUNCTION sync_journal_tenant_id()
                    RETURNS TRIGGER AS \$\$
                    BEGIN
                        IF NEW.tenant_id IS NULL THEN
                            SELECT tenant_id INTO NEW.tenant_id
                            FROM shipments
                            WHERE id = NEW.shipment_id;
                        END IF;
                        RETURN NEW;
                    END;
                    \$\$ LANGUAGE plpgsql;
                ");
                
                // Create triggers
                DB::statement("
                    CREATE TRIGGER journal_tenant_id_sync_insert
                    BEFORE INSERT ON delivery_score_journal
                    FOR EACH ROW
                    EXECUTE FUNCTION sync_journal_tenant_id()
                ");
                
                // Optional: Also handle UPDATE (if shipment_id ever changes)
                DB::statement("
                    CREATE TRIGGER journal_tenant_id_sync_update
                    BEFORE UPDATE ON delivery_score_journal
                    FOR EACH ROW
                    WHEN (OLD.shipment_id IS DISTINCT FROM NEW.shipment_id)
                    EXECUTE FUNCTION sync_journal_tenant_id()
                ");
            } catch (\Throwable $e) {
                // Trigger might already exist, skip
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        // Drop triggers (PostgreSQL)
        if ($driver === 'pgsql') {
            try {
                DB::statement("DROP TRIGGER IF EXISTS journal_tenant_id_sync_insert ON delivery_score_journal");
                DB::statement("DROP TRIGGER IF EXISTS journal_tenant_id_sync_update ON delivery_score_journal");
                DB::statement("DROP FUNCTION IF EXISTS sync_journal_tenant_id()");
            } catch (\Throwable $e) {
                // Trigger/function might not exist, skip
            }
        }
        
        // Drop FK and index
        Schema::table('delivery_score_journal', function (Blueprint $table) {
            try {
                $table->dropForeign('dsj_tenant_fk');
            } catch (\Throwable $e) {
                // FK might not exist, skip
            }
            
            try {
                $table->dropIndex('journal_tenant_created_idx');
            } catch (\Throwable $e) {
                // Index might not exist, skip
            }
        });
        
        // Drop column
        Schema::table('delivery_score_journal', function (Blueprint $table) {
            try {
                $table->dropColumn('tenant_id');
            } catch (\Throwable $e) {
                // Column might not exist, skip
            }
        });
    }
};
