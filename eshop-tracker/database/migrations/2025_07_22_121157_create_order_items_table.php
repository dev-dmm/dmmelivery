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
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('tenant_id'); // For direct querying without joins
            
            // Product Information
            $table->string('product_sku')->nullable(); // Stock Keeping Unit
            $table->string('product_name');
            $table->text('product_description')->nullable();
            $table->string('product_category')->nullable();
            $table->string('product_brand')->nullable();
            $table->string('product_model')->nullable();
            $table->json('product_attributes')->nullable(); // Color, size, etc.
            
            // eShop Product References  
            $table->string('external_product_id')->nullable(); // eShop's product ID
            $table->string('external_variant_id')->nullable(); // For product variations
            $table->string('product_url')->nullable(); // Link back to eShop
            $table->json('product_images')->nullable(); // URLs to product images
            
            // Quantity & Pricing
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2); // Price per item before discounts
            $table->decimal('discount_amount', 10, 2)->default(0); // Discount per item
            $table->decimal('final_unit_price', 10, 2); // Final price per item after discount
            $table->decimal('total_price', 10, 2); // quantity * final_unit_price
            
            // Tax Information
            $table->decimal('tax_rate', 5, 4)->default(0); // e.g., 0.2400 for 24%
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->string('tax_class')->nullable(); // 'standard', 'reduced', 'zero'
            
            // Physical Properties
            $table->decimal('weight', 8, 3)->nullable(); // Weight per item in kg
            $table->json('dimensions')->nullable(); // {length, width, height} in cm
            $table->boolean('is_digital')->default(false); // Digital products don't need shipping
            $table->boolean('requires_special_handling')->default(false);
            $table->boolean('is_fragile')->default(false);
            $table->boolean('is_hazardous')->default(false);
            
            // Inventory & Fulfillment
            $table->enum('fulfillment_status', [
                'pending',          // Waiting to be picked
                'allocated',        // Inventory allocated
                'picked',           // Items picked from warehouse  
                'packed',           // Items packed for shipping
                'shipped',          // Items shipped
                'delivered',        // Items delivered
                'cancelled',        // Item cancelled
                'returned',         // Item returned
                'exchanged'         // Item exchanged
            ])->default('pending');
            
            $table->integer('quantity_shipped')->default(0);
            $table->integer('quantity_delivered')->default(0);
            $table->integer('quantity_returned')->default(0);
            
            // Supplier/Vendor Information
            $table->string('supplier_name')->nullable();
            $table->string('supplier_sku')->nullable();
            $table->decimal('supplier_cost', 10, 2)->nullable(); // Cost from supplier
            
            // Custom Fields for eShop-specific data
            $table->json('custom_fields')->nullable(); // Flexible data storage
            $table->text('special_instructions')->nullable(); // Item-specific handling notes
            
            // Serial Numbers / Tracking for individual items
            $table->json('serial_numbers')->nullable(); // For trackable items
            $table->json('batch_numbers')->nullable(); // For batch-tracked items
            
            // Timestamps
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            
            // Indexes
            $table->index(['order_id']);
            $table->index(['tenant_id', 'fulfillment_status']);
            $table->index(['product_sku', 'tenant_id']);
            $table->index(['external_product_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
