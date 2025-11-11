<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            // PostgreSQL: Use partial unique index to allow multiple NULLs but unique non-NULLs
            try {
                DB::statement("
                    CREATE UNIQUE INDEX IF NOT EXISTS global_customers_fingerprint_unique
                    ON global_customers (hashed_fingerprint)
                    WHERE hashed_fingerprint IS NOT NULL
                ");
            } catch (\Throwable $e) {
                // Index might already exist, skip
            }
        } else {
            // MySQL/SQLite: Standard unique index (allows multiple NULLs in most cases)
            // Note: If hashed_fingerprint can be NULL, consider making it NOT NULL instead
            Schema::table('global_customers', function (Blueprint $table) {
                // DB-level guarantee that no duplicate fingerprints exist
                // Even if a race condition occurs before updateOrCreate
                try {
                    $table->unique('hashed_fingerprint', 'global_customers_fingerprint_unique');
                } catch (\Throwable $e) {
                    // Index might already exist (e.g., from initial table creation), skip
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            try {
                DB::statement("DROP INDEX IF EXISTS global_customers_fingerprint_unique");
            } catch (\Throwable $e) {
                // Index might not exist, skip
            }
        } else {
            Schema::table('global_customers', function (Blueprint $table) {
                try {
                    $table->dropUnique('global_customers_fingerprint_unique');
                } catch (\Throwable $e) {
                    // Index might not exist, skip
                }
            });
        }
    }
};
