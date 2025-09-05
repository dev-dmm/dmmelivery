<?php
// database/migrations/2024_01_01_000003_create_shipments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('order_id')->nullable();
            $table->uuid('customer_id');
            $table->uuid('courier_id');
            $table->string('tracking_number')->unique();
            $table->string('courier_tracking_id');
            $table->enum('status', [
                'pending', 'picked_up', 'in_transit', 'out_for_delivery', 
                'delivered', 'failed', 'returned', 'cancelled'
            ])->default('pending');
            $table->decimal('weight', 8, 2)->nullable();
            $table->json('dimensions')->nullable();
            $table->text('shipping_address');
            $table->text('billing_address')->nullable();
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamp('actual_delivery')->nullable();
            $table->json('courier_response')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'tracking_number']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('shipments');
    }
};