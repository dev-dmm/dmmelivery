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
     * Adds CHECK constraint to ensure reason is always one of the valid final statuses.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ADD CONSTRAINT chk_journal_reason
                    CHECK (reason IN ('delivered','returned','cancelled'))
                ");
            } catch (\Throwable $e) {
                // Constraint might already exist, skip
            }
        } elseif ($driver === 'mysql') {
            // Detect MariaDB vs MySQL
            $versionRow = DB::selectOne('SELECT VERSION() AS v');
            $version = $versionRow->v ?? '';
            $isMaria = stripos($version, 'mariadb') !== false;
            $semver = preg_replace('/[^0-9.].*/', '', $version);
            
            // Enforce CHECK only on MySQL 8.0.16+ AND NOT MariaDB
            if (!$isMaria && version_compare($semver, '8.0.16', '>=')) {
                try {
                    DB::statement("
                        ALTER TABLE delivery_score_journal
                        ADD CONSTRAINT chk_journal_reason
                        CHECK (reason IN ('delivered','returned','cancelled'))
                    ");
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
                DB::statement("ALTER TABLE delivery_score_journal DROP CONSTRAINT IF EXISTS chk_journal_reason");
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
                    DB::statement("ALTER TABLE delivery_score_journal DROP CHECK chk_journal_reason");
                } catch (\Throwable $e) {
                    // Constraint might not exist, skip
                }
            }
            // MariaDB: skip drop for safety (CHECK might not have been added)
        }
    }
};
