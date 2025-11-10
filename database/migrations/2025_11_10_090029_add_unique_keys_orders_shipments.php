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
            $table->unique(['tenant_id', 'external_order_id'], 'orders_tenant_extid_unique');
        });

        Schema::table('shipments', function (Blueprint $table) {
            // If tracking_number must be unique per tenant:
            $table->unique(['tenant_id', 'tracking_number'], 'shipments_tenant_tracking_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_tenant_extid_unique');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique('shipments_tenant_tracking_unique');
        });
    }
};
