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
        Schema::create('import_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable(); // Who initiated the import
            
            // Import Source & Type
            $table->enum('import_type', ['csv', 'xml', 'json', 'api', 'manual'])->default('csv');
            $table->enum('import_method', ['file_upload', 'api_call', 'scheduled', 'webhook'])->default('file_upload');
            $table->string('source_name')->nullable(); // Original filename or API endpoint
            
            // File Information (for file-based imports)
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable(); // Stored file location
            $table->bigInteger('file_size')->nullable(); // File size in bytes
            $table->string('file_hash')->nullable(); // For deduplication
            $table->string('mime_type')->nullable();
            
            // Import Status & Progress
            $table->enum('status', [
                'pending',      // Import queued
                'processing',   // Currently processing
                'completed',    // Successfully completed
                'failed',       // Failed with errors
                'partial',      // Completed with some errors
                'cancelled'     // User cancelled
            ])->default('pending');
            
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->integer('skipped_rows')->default(0);
            
            // Results
            $table->integer('orders_created')->default(0);
            $table->integer('orders_updated')->default(0);
            $table->integer('customers_created')->default(0);
            $table->integer('customers_updated')->default(0);
            
            // Validation & Mapping
            $table->json('field_mapping')->nullable(); // CSV column -> DB field mapping
            $table->json('validation_rules')->nullable(); // Applied validation rules
            $table->text('import_options')->nullable(); // JSON of import settings
            
            // Error Handling
            $table->json('errors')->nullable(); // Array of error messages
            $table->json('warnings')->nullable(); // Array of warning messages
            $table->text('error_log')->nullable(); // Detailed error log
            $table->string('failed_rows_file')->nullable(); // Path to file with failed rows
            
            // Processing Information
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('processing_time_seconds')->nullable();
            $table->string('processed_by')->nullable(); // Job queue name or 'synchronous'
            $table->string('job_id')->nullable(); // Queue job ID for tracking
            
            // Import Configuration
            $table->boolean('create_missing_customers')->default(true);
            $table->boolean('update_existing_orders')->default(false);
            $table->boolean('send_notifications')->default(false);
            $table->boolean('auto_create_shipments')->default(false);
            $table->string('default_status')->default('pending');
            
            // Metadata
            $table->json('metadata')->nullable(); // Extra information about the import
            $table->text('notes')->nullable(); // User or system notes
            $table->string('import_reference')->nullable(); // External reference ID
            
            // API-specific fields
            $table->string('api_endpoint')->nullable(); // For API imports
            $table->json('api_headers')->nullable(); // Headers used in API call
            $table->text('api_payload')->nullable(); // API request payload
            $table->integer('api_response_code')->nullable(); // HTTP response code
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'import_type']);
            $table->index(['created_at', 'tenant_id']);
            $table->index(['file_hash']); // For deduplication
            $table->index(['job_id']); // For queue job tracking
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
