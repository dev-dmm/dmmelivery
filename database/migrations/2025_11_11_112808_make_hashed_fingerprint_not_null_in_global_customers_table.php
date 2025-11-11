<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Makes hashed_fingerprint NOT NULL to enforce data integrity.
     * GlobalCustomerService always generates a fingerprint (throws exception if both email and phone are empty),
     * so this should be safe. However, we check for existing NULLs first.
     * 
     * Uses raw SQL per-driver instead of ->change() to avoid requiring doctrine/dbal.
     */
    public function up(): void
    {
        // Safety check: ensure no NULL values exist
        $nullCount = DB::table('global_customers')
            ->whereNull('hashed_fingerprint')
            ->count();
        
        if ($nullCount > 0) {
            throw new \RuntimeException(
                "Cannot make hashed_fingerprint NOT NULL: {$nullCount} existing records have NULL values. " .
                "Please fix these records first (they should not exist per GlobalCustomerService logic)."
            );
        }
        
        // Make column NOT NULL using raw SQL per-driver (64 chars is enough for SHA-256 hex)
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE global_customers MODIFY COLUMN hashed_fingerprint VARCHAR(64) NOT NULL');
        } elseif ($driver === 'pgsql') {
            // Change type first, then set NOT NULL (proper order when narrowing type)
            DB::statement('ALTER TABLE global_customers ALTER COLUMN hashed_fingerprint TYPE VARCHAR(64)');
            DB::statement('ALTER TABLE global_customers ALTER COLUMN hashed_fingerprint SET NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite has limited ALTER TABLE support - would need table recreation
            // For now, skip (SQLite is typically dev-only)
            // In production with SQLite, consider using a different approach or doctrine/dbal
            Log::warning('SQLite does not support ALTER COLUMN NOT NULL. Skipping migration. Consider using doctrine/dbal or recreating table.');
        } else {
            throw new \RuntimeException("Unsupported database driver: {$driver}. Cannot modify column to NOT NULL.");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE global_customers MODIFY COLUMN hashed_fingerprint VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE global_customers ALTER COLUMN hashed_fingerprint DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite: skip (same limitation as up())
            Log::warning('SQLite does not support ALTER COLUMN. Skipping rollback.');
        }
        // Other drivers: no-op (already handled in up())
    }
};
