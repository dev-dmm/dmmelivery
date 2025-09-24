<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('chat_session_id');
            $table->enum('sender_type', ['customer', 'ai', 'agent'])->default('customer');
            $table->uuid('sender_id')->nullable(); // User ID for agents
            $table->text('message');
            $table->enum('message_type', ['text', 'quick_reply', 'shipment_info', 'action_button', 'error'])->default('text');
            $table->json('metadata')->nullable();
            $table->boolean('is_ai_generated')->default(false);
            $table->decimal('confidence_score', 3, 2)->nullable(); // 0.00 to 1.00
            $table->string('intent')->nullable(); // tracking, delivery, complaint, etc.
            $table->json('entities')->nullable(); // Extracted entities
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['tenant_id', 'chat_session_id']);
            $table->index(['sender_type', 'is_ai_generated']);
            $table->index(['intent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
