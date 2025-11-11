<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // CHECK constraint to ensure scored_delta is only -1, 1, or NULL
        // Prevents out-of-range values and keeps data clean
        // Note: MySQL < 8.0.16 ignores CHECK constraints, MariaDB has different CHECK support
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            // PostgreSQL supports CHECK constraints natively
            try {
                DB::statement("ALTER TABLE shipments ADD CONSTRAINT chk_shipments_scored_delta CHECK (scored_delta IN (-1, 1) OR scored_delta IS NULL)");
            } catch (\Throwable $e) {
                // Constraint might already exist, skip
            }
        } elseif ($driver === 'mysql') {
            // Detect MariaDB vs MySQL
            $versionRow = DB::selectOne('SELECT VERSION() AS v');
            $version = $versionRow->v ?? '';
            $isMaria = stripos($version, 'mariadb') !== false;
            $semver = preg_replace('/[^0-9.].*/', '', $version); // Keep only "8.0.35"
            
            // Enforce CHECK only on MySQL 8.0.16+ AND NOT MariaDB
            if (!$isMaria && version_compare($semver, '8.0.16', '>=')) {
                try {
                    DB::statement("ALTER TABLE shipments ADD CONSTRAINT chk_shipments_scored_delta CHECK (scored_delta IN (-1, 1) OR scored_delta IS NULL)");
                } catch (\Throwable $e) {
                    // Constraint might already exist, skip
                }
            }
        }
        // SQLite, older MySQL versions, and MariaDB: CHECK constraints are parsed but not enforced
        // This is fine - application-level validation still applies
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            try {
                DB::statement("ALTER TABLE shipments DROP CONSTRAINT IF EXISTS chk_shipments_scored_delta");
            } catch (\Throwable $e) {
                // Constraint might not exist, skip
            }
        } elseif ($driver === 'mysql') {
            // Detect MariaDB vs MySQL
            $versionRow = DB::selectOne('SELECT VERSION() AS v');
            $version = $versionRow->v ?? '';
            $isMaria = stripos($version, 'mariadb') !== false;
            $semver = preg_replace('/[^0-9.].*/', '', $version);
            
            // Only drop if we added it (MySQL 8.0.16+ and NOT MariaDB)
            if (!$isMaria && version_compare($semver, '8.0.16', '>=')) {
                try {
                    // MySQL uses DROP CHECK, not DROP CONSTRAINT
                    DB::statement("ALTER TABLE shipments DROP CHECK chk_shipments_scored_delta");
                } catch (\Throwable $e) {
                    // Constraint might not exist, skip
                }
            }
            // MariaDB: skip drop for safety (CHECK might not have been added)
        }
    }
};
