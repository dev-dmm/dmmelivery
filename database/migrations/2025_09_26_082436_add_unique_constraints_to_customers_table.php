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
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        // Check if unique constraint for email already exists
        $emailIndexExists = DB::selectOne(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = 'customers' 
             AND index_name = 'customers_tenant_email_unique'",
            [$databaseName]
        );
        
        // Check if unique constraint for phone already exists
        $phoneIndexExists = DB::selectOne(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = 'customers' 
             AND index_name = 'customers_tenant_phone_unique'",
            [$databaseName]
        );
        
        Schema::table('customers', function (Blueprint $table) use ($emailIndexExists, $phoneIndexExists) {
            // Add unique constraint for email within tenant (only if it doesn't exist)
            if ($emailIndexExists->count == 0) {
                $table->unique(['tenant_id', 'email'], 'customers_tenant_email_unique');
            }
            
            // Add unique constraint for phone within tenant (only if phone is not null)
            // Note: This will only apply to non-null phone numbers
            if ($phoneIndexExists->count == 0) {
                $table->unique(['tenant_id', 'phone'], 'customers_tenant_phone_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        // Check if constraints exist before dropping
        $emailIndexExists = DB::selectOne(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = 'customers' 
             AND index_name = 'customers_tenant_email_unique'",
            [$databaseName]
        );
        
        $phoneIndexExists = DB::selectOne(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = 'customers' 
             AND index_name = 'customers_tenant_phone_unique'",
            [$databaseName]
        );
        
        Schema::table('customers', function (Blueprint $table) use ($emailIndexExists, $phoneIndexExists) {
            if ($emailIndexExists->count > 0) {
                $table->dropUnique('customers_tenant_email_unique');
            }
            if ($phoneIndexExists->count > 0) {
                $table->dropUnique('customers_tenant_phone_unique');
            }
        });
    }
};
