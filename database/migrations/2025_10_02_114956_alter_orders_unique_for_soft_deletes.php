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
        
        // Check if the old unique constraint exists
        $oldIndexExists = DB::selectOne(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = 'orders' 
             AND index_name = 'orders_external_order_id_tenant_id_unique'",
            [$databaseName]
        );
        
        // Check if the new unique constraint already exists
        $newIndexExists = DB::selectOne(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = 'orders' 
             AND index_name = 'orders_ext_tenant_deleted_unique'",
            [$databaseName]
        );
        
        Schema::table('orders', function (Blueprint $table) use ($oldIndexExists, $newIndexExists) {
            // Drop the existing unique constraint (only if it exists)
            if ($oldIndexExists->count > 0) {
                $table->dropUnique('orders_external_order_id_tenant_id_unique');
            }
            
            // Add new unique constraint that includes deleted_at (only if it doesn't exist)
            if ($newIndexExists->count == 0) {
                $table->unique(
                    ['external_order_id', 'tenant_id', 'deleted_at'],
                    'orders_ext_tenant_deleted_unique'
                );
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
        
        // Check if the new unique constraint exists
        $newIndexExists = DB::selectOne(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = 'orders' 
             AND index_name = 'orders_ext_tenant_deleted_unique'",
            [$databaseName]
        );
        
        // Check if the old unique constraint exists
        $oldIndexExists = DB::selectOne(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = 'orders' 
             AND index_name = 'orders_external_order_id_tenant_id_unique'",
            [$databaseName]
        );
        
        Schema::table('orders', function (Blueprint $table) use ($newIndexExists, $oldIndexExists) {
            // Drop the new unique constraint (only if it exists)
            if ($newIndexExists->count > 0) {
                $table->dropUnique('orders_ext_tenant_deleted_unique');
            }
            
            // Restore the original unique constraint (only if it doesn't exist)
            if ($oldIndexExists->count == 0) {
                $table->unique(
                    ['external_order_id', 'tenant_id'],
                    'orders_external_order_id_tenant_id_unique'
                );
            }
        });
    }
};
