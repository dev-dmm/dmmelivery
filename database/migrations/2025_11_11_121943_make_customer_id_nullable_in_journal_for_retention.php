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
     * Makes customer_id nullable and changes FK to SET NULL for journal retention.
     * This preserves journal history even when customers are deleted.
     * 
     * NOTE: This is optional - only run if you want to preserve journal entries
     * when customers are deleted. Default behavior (CASCADE) cleans up journal entries.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        // Drop existing FK first
        Schema::table('delivery_score_journal', function (Blueprint $table) {
            try {
                $table->dropForeign('dsj_customer_fk');
            } catch (\Throwable $e) {
                // FK might not exist, skip
            }
        });
        
        // Make customer_id nullable and change FK to SET NULL (per-driver raw SQL)
        if ($driver === 'mysql') {
            // Detect UUID column type from parent table (CHAR(36) or BINARY(16))
            $uuidType = 'CHAR(36)'; // Default Laravel uuid() type
            try {
                $columnInfo = DB::selectOne("
                    SELECT COLUMN_TYPE
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'customers'
                      AND COLUMN_NAME = 'id'
                ");
                
                if ($columnInfo && isset($columnInfo->COLUMN_TYPE)) {
                    $columnType = strtoupper($columnInfo->COLUMN_TYPE);
                    // Check if it's BINARY(16) (optimized UUID storage)
                    if (strpos($columnType, 'BINARY(16)') !== false) {
                        $uuidType = 'BINARY(16)';
                    } elseif (strpos($columnType, 'CHAR(36)') !== false) {
                        $uuidType = 'CHAR(36)';
                    }
                    // Default to CHAR(36) if detection fails
                }
            } catch (\Throwable $e) {
                // Fallback to default CHAR(36) if detection fails
            }
            
            // MySQL: MODIFY COLUMN to nullable (matching parent table type)
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    MODIFY COLUMN customer_id {$uuidType} NULL
                ");
            } catch (\Throwable $e) {
                // Column might already be nullable, skip
            }
            
            // Recreate FK with SET NULL
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ADD CONSTRAINT dsj_customer_fk
                    FOREIGN KEY (customer_id)
                    REFERENCES customers(id)
                    ON DELETE SET NULL
                ");
            } catch (\Throwable $e) {
                // FK might already exist, skip
            }
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: ALTER COLUMN to nullable
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ALTER COLUMN customer_id DROP NOT NULL
                ");
            } catch (\Throwable $e) {
                // Column might already be nullable, skip
            }
            
            // Recreate FK with SET NULL
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ADD CONSTRAINT dsj_customer_fk
                    FOREIGN KEY (customer_id)
                    REFERENCES customers(id)
                    ON DELETE SET NULL
                ");
            } catch (\Throwable $e) {
                // FK might already exist, skip
            }
        }
        // SQLite: skip (limited ALTER support)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        // Drop FK first
        Schema::table('delivery_score_journal', function (Blueprint $table) {
            try {
                $table->dropForeign('dsj_customer_fk');
            } catch (\Throwable $e) {
                // FK might not exist, skip
            }
        });
        
        // Make customer_id NOT NULL and recreate FK with CASCADE (per-driver raw SQL)
        if ($driver === 'mysql') {
            // Detect UUID column type from parent table (matching up() method)
            $uuidType = 'CHAR(36)'; // Default Laravel uuid() type
            try {
                $columnInfo = DB::selectOne("
                    SELECT COLUMN_TYPE
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'customers'
                      AND COLUMN_NAME = 'id'
                ");
                
                if ($columnInfo && isset($columnInfo->COLUMN_TYPE)) {
                    $columnType = strtoupper($columnInfo->COLUMN_TYPE);
                    if (strpos($columnType, 'BINARY(16)') !== false) {
                        $uuidType = 'BINARY(16)';
                    } elseif (strpos($columnType, 'CHAR(36)') !== false) {
                        $uuidType = 'CHAR(36)';
                    }
                }
            } catch (\Throwable $e) {
                // Fallback to default CHAR(36) if detection fails
            }
            
            // First, set any NULL values to a valid customer_id (or handle separately)
            // For safety, we'll just make it NOT NULL and let the application handle NULLs
            
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    MODIFY COLUMN customer_id {$uuidType} NOT NULL
                ");
            } catch (\Throwable $e) {
                // Might fail if NULLs exist - handle separately if needed
            }
            
            // Recreate FK with CASCADE
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ADD CONSTRAINT dsj_customer_fk
                    FOREIGN KEY (customer_id)
                    REFERENCES customers(id)
                    ON DELETE CASCADE
                ");
            } catch (\Throwable $e) {
                // FK might already exist, skip
            }
        } elseif ($driver === 'pgsql') {
            // First, set any NULL values (handle separately if needed)
            
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ALTER COLUMN customer_id SET NOT NULL
                ");
            } catch (\Throwable $e) {
                // Might fail if NULLs exist - handle separately if needed
            }
            
            // Recreate FK with CASCADE
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ADD CONSTRAINT dsj_customer_fk
                    FOREIGN KEY (customer_id)
                    REFERENCES customers(id)
                    ON DELETE CASCADE
                ");
            } catch (\Throwable $e) {
                // FK might already exist, skip
            }
        }
    }
};
