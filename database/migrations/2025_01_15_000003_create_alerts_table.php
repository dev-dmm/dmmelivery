<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('alert_rule_id');
            $table->uuid('shipment_id');
            $table->string('title');
            $table->text('description');
            $table->enum('alert_type', ['delay', 'stuck', 'route_deviation', 'weather_impact', 'courier_performance', 'predictive_delay'])->default('delay');
            $table->enum('severity_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['active', 'acknowledged', 'resolved'])->default('active');
            $table->timestamp('triggered_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->uuid('acknowledged_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->integer('escalation_level')->default(0);
            $table->boolean('notification_sent')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('alert_rule_id')->references('id')->on('alert_rules')->onDelete('cascade');
            $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('cascade');
            $table->foreign('acknowledged_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['tenant_id', 'status']);
            $table->index(['severity_level', 'status']);
            $table->index(['triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
