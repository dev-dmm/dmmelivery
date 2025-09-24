<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictive_etas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('shipment_id');
            $table->timestamp('original_eta')->nullable();
            $table->timestamp('predicted_eta')->nullable();
            $table->decimal('confidence_score', 3, 2)->default(0.5); // 0.00 to 1.00
            $table->enum('delay_risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->json('delay_factors')->nullable(); // Weather, traffic, courier performance, etc.
            $table->decimal('weather_impact', 3, 2)->default(0); // 0.00 to 1.00
            $table->decimal('traffic_impact', 3, 2)->default(0); // 0.00 to 1.00
            $table->decimal('historical_accuracy', 3, 2)->default(0.8); // 0.00 to 1.00
            $table->json('route_optimization_suggestions')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('cascade');
            
            $table->index(['tenant_id', 'delay_risk_level']);
            $table->index(['shipment_id']);
            $table->index(['predicted_eta']);
            $table->index(['last_updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictive_etas');
    }
};
