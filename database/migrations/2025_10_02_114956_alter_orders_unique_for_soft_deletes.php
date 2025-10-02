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
        Schema::table('orders', function (Blueprint $table) {
            // Drop the existing unique constraint
            $table->dropUnique('orders_external_order_id_tenant_id_unique');
            
            // Add new unique constraint that includes deleted_at
            $table->unique(
                ['external_order_id', 'tenant_id', 'deleted_at'],
                'orders_ext_tenant_deleted_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique('orders_ext_tenant_deleted_unique');
            
            // Restore the original unique constraint
            $table->unique(
                ['external_order_id', 'tenant_id'],
                'orders_external_order_id_tenant_id_unique'
            );
        });
    }
};
