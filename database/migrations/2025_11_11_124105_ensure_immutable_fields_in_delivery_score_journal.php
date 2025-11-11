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
     * Ensures immutable fields (delta, reason, created_at) are NOT NULL
     * and without ON UPDATE triggers to maintain audit trail integrity.
     * This migration is idempotent and safe to run multiple times.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        // Ensure delta is NOT NULL (immutable audit field)
        if ($driver === 'mysql') {
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    MODIFY COLUMN delta SMALLINT NOT NULL
                ");
            } catch (\Throwable $e) {
                // Column might already be NOT NULL, skip
            }
        } elseif ($driver === 'pgsql') {
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ALTER COLUMN delta SET NOT NULL
                ");
            } catch (\Throwable $e) {
                // Column might already be NOT NULL, skip
            }
        }
        
        // Ensure reason is NOT NULL (immutable audit field)
        if ($driver === 'mysql') {
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    MODIFY COLUMN reason VARCHAR(32) NOT NULL
                ");
            } catch (\Throwable $e) {
                // Column might already be NOT NULL, skip
            }
        } elseif ($driver === 'pgsql') {
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ALTER COLUMN reason SET NOT NULL
                ");
            } catch (\Throwable $e) {
                // Column might already be NOT NULL, skip
            }
        }
        
        // Ensure created_at is NOT NULL (immutable audit field)
        // Note: Laravel's useCurrent() already sets NOT NULL, but we ensure it explicitly
        // IMPORTANT: We explicitly avoid ON UPDATE CURRENT_TIMESTAMP to keep created_at immutable
        if ($driver === 'mysql') {
            try {
                // Check if column has ON UPDATE CURRENT_TIMESTAMP and remove it if present
                $columnInfo = DB::selectOne("
                    SELECT COLUMN_TYPE, EXTRA
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'delivery_score_journal'
                      AND COLUMN_NAME = 'created_at'
                ");
                
                // Remove ON UPDATE if present (ensure immutability)
                // MODIFY without ON UPDATE explicitly removes any existing ON UPDATE clause
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    MODIFY COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ");
            } catch (\Throwable $e) {
                // Column might already be NOT NULL, skip
            }
        } elseif ($driver === 'pgsql') {
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ALTER COLUMN created_at SET NOT NULL
                ");
            } catch (\Throwable $e) {
                // Column might already be NOT NULL, skip
            }
        }
        
        // PostgreSQL: Add trigger to prevent UPDATE on immutable fields (extra safety)
        if ($driver === 'pgsql') {
            // Get current schema (defaults to 'public' if not specified)
            $schema = DB::selectOne("SELECT current_schema() as schema")->schema ?? 'public';
            
            // Drop trigger if exists (idempotent)
            try {
                DB::statement("DROP TRIGGER IF EXISTS prevent_immutable_update ON {$schema}.delivery_score_journal");
            } catch (\Throwable $e) {
                // Ignore
            }
            
            // Create function to check immutable fields (with schema qualification)
            try {
                DB::statement("
                    CREATE OR REPLACE FUNCTION {$schema}.check_immutable_fields()
                    RETURNS TRIGGER AS \$\$
                    BEGIN
                        -- Prevent changes to immutable fields
                        IF OLD.delta IS DISTINCT FROM NEW.delta THEN
                            RAISE EXCEPTION 'Cannot update immutable field: delta';
                        END IF;
                        IF OLD.reason IS DISTINCT FROM NEW.reason THEN
                            RAISE EXCEPTION 'Cannot update immutable field: reason';
                        END IF;
                        IF OLD.created_at IS DISTINCT FROM NEW.created_at THEN
                            RAISE EXCEPTION 'Cannot update immutable field: created_at';
                        END IF;
                        IF OLD.id IS DISTINCT FROM NEW.id THEN
                            RAISE EXCEPTION 'Cannot update immutable field: id (primary key)';
                        END IF;
                        RETURN NEW;
                    END;
                    \$\$ LANGUAGE plpgsql;
                ");
            } catch (\Throwable $e) {
                // Function might already exist, skip
            }
            
            // Create trigger (with schema qualification)
            try {
                DB::statement("
                    CREATE TRIGGER prevent_immutable_update
                    BEFORE UPDATE ON {$schema}.delivery_score_journal
                    FOR EACH ROW
                    EXECUTE FUNCTION {$schema}.check_immutable_fields()
                ");
            } catch (\Throwable $e) {
                // Trigger might already exist, skip
            }
        }
        
        // MySQL: Add trigger to prevent UPDATE on immutable fields (DB-level protection)
        if ($driver === 'mysql') {
            try {
                DB::unprepared("
                    DROP TRIGGER IF EXISTS trg_dsj_prevent_mutable_update;
                    CREATE TRIGGER trg_dsj_prevent_mutable_update
                    BEFORE UPDATE ON delivery_score_journal
                    FOR EACH ROW
                    BEGIN
                        IF NEW.delta <> OLD.delta THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable: delta';
                        END IF;
                        IF NEW.reason <> OLD.reason THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable: reason';
                        END IF;
                        IF NEW.created_at <> OLD.created_at THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable: created_at';
                        END IF;
                        IF NEW.id <> OLD.id THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable: id';
                        END IF;
                    END
                ");
            } catch (\Throwable $e) {
                // Trigger might already exist or MySQL version doesn't support, skip
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        // Drop PostgreSQL trigger and function
        if ($driver === 'pgsql') {
            $schema = DB::selectOne("SELECT current_schema() as schema")->schema ?? 'public';
            
            try {
                DB::statement("DROP TRIGGER IF EXISTS prevent_immutable_update ON {$schema}.delivery_score_journal");
            } catch (\Throwable $e) {
                // Ignore
            }
            
            try {
                DB::statement("DROP FUNCTION IF EXISTS {$schema}.check_immutable_fields()");
            } catch (\Throwable $e) {
                // Ignore
            }
        }
        
        // Drop MySQL trigger
        if ($driver === 'mysql') {
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_dsj_prevent_mutable_update");
            } catch (\Throwable $e) {
                // Ignore
            }
        }
        
        // Note: We don't remove NOT NULL constraints in down() as they're part of the schema design
        // If you need to rollback, you'd need to explicitly allow NULLs (not recommended)
    }
};
