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
        Schema::table('tenants', function (Blueprint $table) {
            // Add business settings fields that are expected by the frontend form
            $table->string('business_name')->nullable()->after('name');
            $table->string('default_currency', 3)->default('EUR')->after('business_name');
            $table->decimal('tax_rate', 5, 2)->default(24.00)->after('default_currency');
            $table->decimal('shipping_cost', 10, 2)->default(0.00)->after('tax_rate');
            $table->boolean('auto_create_shipments')->default(false)->after('shipping_cost');
            $table->boolean('send_notifications')->default(true)->after('auto_create_shipments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'business_name',
                'default_currency',
                'tax_rate',
                'shipping_cost',
                'auto_create_shipments',
                'send_notifications'
            ]);
        });
    }
};
