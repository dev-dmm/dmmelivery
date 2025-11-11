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
     * Creates PostgreSQL ENUM type for reason field (more strict than CHECK constraint).
     * Optional: only run if you want stricter schema-level validation.
     * Note: With ENUM, the CHECK constraint becomes redundant (but harmless to keep).
     */
    public function up(): void
    {
        // PostgreSQL only - MySQL doesn't support ENUM for this use case
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        
        // Create ENUM type if it doesn't exist
        try {
            DB::statement("
                DO \$\$ BEGIN
                    CREATE TYPE delivery_reason AS ENUM ('delivered','returned','cancelled');
                EXCEPTION WHEN duplicate_object THEN NULL;
                END \$\$;
            ");
        } catch (\Throwable $e) {
            // Type might already exist, skip
        }
        
        // Convert reason column to use ENUM type
        try {
            DB::statement("
                ALTER TABLE delivery_score_journal
                ALTER COLUMN reason TYPE delivery_reason USING reason::delivery_reason
            ");
        } catch (\Throwable $e) {
            // Column might already be ENUM, skip
        }
        
        // Optional: Drop CHECK constraint since ENUM is more strict
        // (Keeping it is harmless, but ENUM provides the same validation)
        // Uncomment if you want to remove the redundant CHECK:
        /*
        try {
            DB::statement("
                ALTER TABLE delivery_score_journal
                DROP CONSTRAINT IF EXISTS chk_journal_reason
            ");
        } catch (\Throwable $e) {
            // Constraint might not exist, skip
        }
        */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        
        // Convert back to VARCHAR
        try {
            DB::statement("
                ALTER TABLE delivery_score_journal
                ALTER COLUMN reason TYPE VARCHAR(32)
                USING reason::TEXT
            ");
        } catch (\Throwable $e) {
            // Column might not be ENUM, skip
        }
        
        // Drop ENUM type (only if no other tables use it)
        // Be careful: if other tables use this type, don't drop it
        try {
            $usage = DB::selectOne("
                SELECT COUNT(*) as c
                FROM information_schema.columns
                WHERE udt_name = 'delivery_reason'
                  AND table_schema = current_schema()
                  AND table_name != 'delivery_score_journal'
            ");
            
            if (($usage->c ?? 0) === 0) {
                DB::statement("DROP TYPE IF EXISTS delivery_reason");
            }
        } catch (\Throwable $e) {
            // Type might be in use or not exist, skip
        }
    }
};
