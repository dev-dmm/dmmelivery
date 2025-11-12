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
        
        // Add indexes to customers table
        if (!$this->indexExists('customers', 'customers_tenant_id_email_index', $driver)) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index(['tenant_id', 'email'], 'customers_tenant_id_email_index');
            });
        }
        
        if (!$this->indexExists('customers', 'customers_tenant_id_phone_index', $driver)) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index(['tenant_id', 'phone'], 'customers_tenant_id_phone_index');
            });
        }

        // Add indexes to shipments table
        if (!$this->indexExists('shipments', 'shipments_global_customer_id_status_index', $driver)) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->index(['global_customer_id', 'status'], 'shipments_global_customer_id_status_index');
            });
        }
    }
    
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName, string $driver): bool
    {
        $database = DB::connection()->getDatabaseName();
        
        if ($driver === 'mysql') {
            $result = DB::select(
                "SELECT COUNT(*) as count FROM information_schema.statistics 
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                [$database, $table, $indexName]
            );
            return $result[0]->count > 0;
        } elseif ($driver === 'pgsql') {
            $result = DB::select(
                "SELECT COUNT(*) as count FROM pg_indexes 
                 WHERE schemaname = 'public' AND tablename = ? AND indexname = ?",
                [$table, $indexName]
            );
            return $result[0]->count > 0;
        }
        
        // For other drivers, try to create and catch exception
        return false;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            try {
                $table->dropIndex('customers_tenant_id_email_index');
            } catch (\Exception $e) {
                // Index might not exist, skip
            }
            
            try {
                $table->dropIndex('customers_tenant_id_phone_index');
            } catch (\Exception $e) {
                // Index might not exist, skip
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            try {
                $table->dropIndex('shipments_global_customer_id_status_index');
            } catch (\Exception $e) {
                // Index might not exist, skip
            }
        });
    }
};
