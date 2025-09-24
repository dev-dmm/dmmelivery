<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('trigger_conditions'); // Array of conditions
            $table->enum('alert_type', ['delay', 'stuck', 'route_deviation', 'weather_impact', 'courier_performance', 'predictive_delay'])->default('delay');
            $table->enum('severity_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->json('notification_channels')->default('["email"]'); // email, sms, slack, webhook
            $table->json('escalation_rules')->nullable(); // Auto-escalation rules
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('trigger_count')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'is_active']);
            $table->index(['alert_type', 'severity_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
