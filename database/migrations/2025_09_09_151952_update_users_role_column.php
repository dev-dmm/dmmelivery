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
        // SQLite doesn't support MODIFY COLUMN, so we need to recreate the table
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, we need to recreate the table
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
            
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 50)->default('user')->after('phone');
            });
        } else {
            // For MySQL/PostgreSQL
            DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original ENUM values
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'user'");
    }
};