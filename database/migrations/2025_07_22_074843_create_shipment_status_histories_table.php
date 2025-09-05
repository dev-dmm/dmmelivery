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
        Schema::create('shipment_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipment_id');
            $table->enum('status', [
                'pending', 'picked_up', 'in_transit', 'out_for_delivery', 
                'delivered', 'failed', 'returned', 'cancelled'
            ]);
            $table->string('description')->nullable();
            $table->string('location')->nullable();
            $table->json('courier_response')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('cascade');
            $table->index(['shipment_id', 'happened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_status_histories');
    }
};
