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
        Schema::create('courier_report_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            
            // File Information
            $table->string('file_name');
            $table->string('file_path');
            $table->bigInteger('file_size');
            $table->string('file_hash');
            $table->string('mime_type');
            
            // Import Status & Progress
            $table->string('status')->default('pending'); // pending, processing, completed, failed, partial
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('matched_rows')->default(0);
            $table->integer('unmatched_rows')->default(0);
            $table->integer('price_mismatch_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            
            // Processing Information
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('processing_time_seconds')->nullable();
            
            // Results Summary
            $table->json('results_summary')->nullable(); // Summary of matches, mismatches, etc.
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->text('error_log')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        // Add foreign key constraints for UUID fields
        Schema::table('courier_report_imports', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courier_report_imports');
    }
};
