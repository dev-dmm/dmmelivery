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
     * Adds partial index for PostgreSQL to optimize queries filtering by scored shipments.
     * Only indexes rows where scored_at IS NOT NULL, making it more efficient than full index.
     * MySQL already has composite index (tenant_id, scored_at) which covers this use case.
     */
    public function up(): void
    {
        // PostgreSQL supports partial indexes (filtered indexes)
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement("
                    CREATE INDEX IF NOT EXISTS shipments_scored_only_idx
                    ON shipments (tenant_id, scored_at)
                    WHERE scored_at IS NOT NULL
                ");
            } catch (\Throwable $e) {
                // Index might already exist or error occurred, skip
            }
        }
        // MySQL doesn't support partial indexes, but composite index already exists
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement("DROP INDEX IF EXISTS shipments_scored_only_idx");
            } catch (\Throwable $e) {
                // Index might not exist, skip
            }
        }
    }
};
