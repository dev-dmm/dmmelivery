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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('customer_id')->nullable(); // Link to existing customers or create new
            
            // Order Identification
            $table->string('external_order_id'); // eShop's order ID
            $table->string('order_number')->nullable(); // Human-readable order number
            $table->string('import_source')->default('manual'); // 'api', 'csv', 'xml', 'manual'
            $table->uuid('import_log_id')->nullable(); // Link to import batch
            
            // Order Status
            $table->enum('status', [
                'pending',          // Just imported, needs processing
                'processing',       // Being prepared for shipment
                'ready_to_ship',    // Ready to create shipment
                'shipped',          // Shipment created
                'delivered',        // Successfully delivered
                'cancelled',        // Order cancelled
                'returned',         // Order returned
                'failed'           // Failed to process/ship
            ])->default('pending');
            
            // Customer Information (for cases where customer doesn't exist)
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            
            // Shipping Address
            $table->text('shipping_address');
            $table->string('shipping_city');
            $table->string('shipping_postal_code');
            $table->string('shipping_country', 2)->default('GR');
            $table->text('shipping_notes')->nullable();
            
            // Billing Address (optional, can be same as shipping)
            $table->text('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_postal_code')->nullable();
            $table->string('billing_country', 2)->nullable();
            
            // Order Totals
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            
            // Payment Information
            $table->enum('payment_status', [
                'pending', 'paid', 'failed', 'refunded', 'partially_refunded'
            ])->default('pending');
            $table->string('payment_method')->nullable(); // 'credit_card', 'paypal', 'bank_transfer', 'cod'
            $table->string('payment_reference')->nullable(); // Transaction ID
            $table->timestamp('payment_date')->nullable();
            
            // Shipping Preferences
            $table->string('preferred_courier')->nullable(); // 'acs', 'elta', 'speedex', etc.
            $table->enum('shipping_method', [
                'standard', 'express', 'overnight', 'pickup_point', 'store_pickup'
            ])->default('standard');
            $table->boolean('requires_signature')->default(false);
            $table->boolean('fragile_items')->default(false);
            $table->decimal('total_weight', 8, 3)->nullable(); // in kg
            $table->json('package_dimensions')->nullable(); // {length, width, height} in cm
            
            // Special Instructions
            $table->text('special_instructions')->nullable();
            $table->json('delivery_preferences')->nullable(); // Time preferences, etc.
            
            // Order Dates
            $table->timestamp('order_date')->useCurrent(); // When order was placed in eShop
            $table->timestamp('expected_ship_date')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            
            // Linked Shipment
            $table->uuid('shipment_id')->nullable(); // Link to created shipment
            
            // Metadata
            $table->json('additional_data')->nullable(); // Extra eShop-specific data
            $table->text('import_notes')->nullable(); // Notes from import process
            
            $table->timestamps();
            $table->softDeletes(); // For safe deletion
            
            // Foreign Keys (import_log_id will be added after import_logs table is created)
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('set null');
            
            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['external_order_id', 'tenant_id']);
            $table->index(['order_date', 'tenant_id']);
            $table->index(['customer_email', 'tenant_id']);
            $table->unique(['external_order_id', 'tenant_id']); // Prevent duplicate imports
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
