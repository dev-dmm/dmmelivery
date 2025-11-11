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
     * Adds millisecond precision to created_at for better ordering and replication.
     * Optional: only run if you need microsecond-level ordering or replication support.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // MySQL 8+ supports fractional seconds precision
            $version = DB::selectOne('SELECT VERSION() AS v')->v ?? '';
            $semver = preg_replace('/[^0-9.].*/', '', $version);
            
            if (version_compare($semver, '8.0', '>=')) {
                try {
                    // Set precision with DEFAULT, explicitly without ON UPDATE
                    // Note: MODIFY COLUMN with DEFAULT CURRENT_TIMESTAMP(3) doesn't add ON UPDATE by default
                    DB::statement("
                        ALTER TABLE delivery_score_journal
                        MODIFY COLUMN created_at TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3)
                    ");
                } catch (\Throwable $e) {
                    // Column might already have precision, skip
                }
            }
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: timestampTz with precision
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ALTER COLUMN created_at TYPE TIMESTAMPTZ(3)
                    USING created_at::TIMESTAMPTZ
                ");
            } catch (\Throwable $e) {
                // Column might already have precision, skip
            }
        }
        // SQLite: skip (limited precision support)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ");
            } catch (\Throwable $e) {
                // Column might not have precision, skip
            }
        } elseif ($driver === 'pgsql') {
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ALTER COLUMN created_at TYPE TIMESTAMP
                    USING created_at::TIMESTAMP
                ");
            } catch (\Throwable $e) {
                // Column might not have precision, skip
            }
        }
    }
};
