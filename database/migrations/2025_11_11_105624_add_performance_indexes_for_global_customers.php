<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Add composite indexes for common lookups
            // Check if indexes already exist to avoid errors on re-run
            try {
                $table->index(['tenant_id', 'email'], 'customers_tenant_id_email_index');
            } catch (\Exception $e) {
                // Index might already exist, skip
            }
            
            try {
                $table->index(['tenant_id', 'phone'], 'customers_tenant_id_phone_index');
            } catch (\Exception $e) {
                // Index might already exist, skip
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            // Add composite index for global customer status lookups
            try {
                $table->index(['global_customer_id', 'status'], 'shipments_global_customer_id_status_index');
            } catch (\Exception $e) {
                // Index might already exist, skip
            }
        });
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
