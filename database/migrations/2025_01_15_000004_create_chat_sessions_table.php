<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('customer_id')->nullable();
            $table->string('session_id')->unique();
            $table->enum('status', ['active', 'resolved', 'escalated'])->default('active');
            $table->string('language', 5)->default('en');
            $table->json('context_data')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamp('resolved_at')->nullable();
            $table->integer('satisfaction_rating')->nullable(); // 1-5
            $table->text('satisfaction_feedback')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->index(['tenant_id', 'status']);
            $table->index(['session_id']);
            $table->index(['last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
