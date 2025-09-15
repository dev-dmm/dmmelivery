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
        // Remove foreign key constraint from orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['import_log_id']);
            $table->dropColumn('import_log_id');
        });

        // Drop the import_logs table
        Schema::dropIfExists('import_logs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate import_logs table (simplified version)
        Schema::create('import_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable();
            $table->enum('import_type', ['csv', 'xml', 'json', 'api', 'manual'])->default('csv');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'partial', 'cancelled'])->default('pending');
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->integer('orders_created')->default(0);
            $table->integer('orders_updated')->default(0);
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // Add import_log_id back to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('import_log_id')->nullable()->after('id');
            $table->foreign('import_log_id')->references('id')->on('import_logs')->onDelete('set null');
        });
    }
};