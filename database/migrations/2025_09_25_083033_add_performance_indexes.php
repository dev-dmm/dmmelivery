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
        // Shipments table indexes
        Schema::table('shipments', function (Blueprint $table) {
            // Composite index for tenant + status (most common query)
            $table->index(['tenant_id', 'status'], 'idx_shipments_tenant_status');
            
            // Tracking number lookup
            $table->index('tracking_number', 'idx_shipments_tracking_number');
            
            // Courier tracking ID lookup
            $table->index('courier_tracking_id', 'idx_shipments_courier_tracking_id');
            
            // Created date for time-based queries
            $table->index('created_at', 'idx_shipments_created_at');
            
            // Customer relationship
            $table->index('customer_id', 'idx_shipments_customer_id');
            
            // Courier relationship
            $table->index('courier_id', 'idx_shipments_courier_id');
        });

        // Orders table indexes
        Schema::table('orders', function (Blueprint $table) {
            // Composite index for tenant + status
            $table->index(['tenant_id', 'status'], 'idx_orders_tenant_status');
            
            // External order ID lookup
            $table->index('external_order_id', 'idx_orders_external_order_id');
            
            // Order number lookup
            $table->index('order_number', 'idx_orders_order_number');
            
            // Customer relationship
            $table->index('customer_id', 'idx_orders_customer_id');
            
            // Created date for time-based queries
            $table->index('created_at', 'idx_orders_created_at');
            
            // Payment status for financial queries
            $table->index('payment_status', 'idx_orders_payment_status');
        });

        // Shipment status history indexes
        Schema::table('shipment_status_histories', function (Blueprint $table) {
            // Shipment relationship (most common query)
            $table->index('shipment_id', 'idx_status_history_shipment_id');
            
            // Status lookup
            $table->index('status', 'idx_status_history_status');
            
            // Happened at for time-based queries
            $table->index('happened_at', 'idx_status_history_happened_at');
            
            // Composite index for shipment + happened_at
            $table->index(['shipment_id', 'happened_at'], 'idx_status_history_shipment_time');
        });

        // Customers table indexes
        Schema::table('customers', function (Blueprint $table) {
            // Tenant relationship
            $table->index('tenant_id', 'idx_customers_tenant_id');
            
            // Email lookup
            $table->index('email', 'idx_customers_email');
            
            // Phone lookup
            $table->index('phone', 'idx_customers_phone');
        });

        // Couriers table indexes
        Schema::table('couriers', function (Blueprint $table) {
            // Tenant relationship
            $table->index('tenant_id', 'idx_couriers_tenant_id');
            
            // Active couriers
            $table->index('is_active', 'idx_couriers_is_active');
            
            // Code lookup
            $table->index('code', 'idx_couriers_code');
        });

        // Predictive ETAs table indexes
        Schema::table('predictive_etas', function (Blueprint $table) {
            // Shipment relationship
            $table->index('shipment_id', 'idx_predictive_etas_shipment_id');
            
            // Tenant relationship
            $table->index('tenant_id', 'idx_predictive_etas_tenant_id');
            
            // Last updated for refresh queries
            $table->index('last_updated_at', 'idx_predictive_etas_updated_at');
        });

        // Alerts table indexes
        Schema::table('alerts', function (Blueprint $table) {
            // Tenant relationship
            $table->index('tenant_id', 'idx_alerts_tenant_id');
            
            // Shipment relationship
            $table->index('shipment_id', 'idx_alerts_shipment_id');
            
            // Status lookup
            $table->index('status', 'idx_alerts_status');
            
            // Alert type lookup
            $table->index('alert_type', 'idx_alerts_type');
            
            // Triggered at for time-based queries
            $table->index('triggered_at', 'idx_alerts_triggered_at');
        });

        // Notification logs table indexes
        Schema::table('notification_logs', function (Blueprint $table) {
            // Tenant relationship
            $table->index('tenant_id', 'idx_notification_logs_tenant_id');
            
            // Shipment relationship
            $table->index('shipment_id', 'idx_notification_logs_shipment_id');
            
            // Status lookup
            $table->index('status', 'idx_notification_logs_status');
            
            // Channel lookup
            $table->index('channel', 'idx_notification_logs_channel');
            
            // Created at for time-based queries
            $table->index('created_at', 'idx_notification_logs_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes in reverse order
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex('idx_notification_logs_created_at');
            $table->dropIndex('idx_notification_logs_channel');
            $table->dropIndex('idx_notification_logs_status');
            $table->dropIndex('idx_notification_logs_shipment_id');
            $table->dropIndex('idx_notification_logs_tenant_id');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex('idx_alerts_triggered_at');
            $table->dropIndex('idx_alerts_type');
            $table->dropIndex('idx_alerts_status');
            $table->dropIndex('idx_alerts_shipment_id');
            $table->dropIndex('idx_alerts_tenant_id');
        });

        Schema::table('predictive_etas', function (Blueprint $table) {
            $table->dropIndex('idx_predictive_etas_updated_at');
            $table->dropIndex('idx_predictive_etas_tenant_id');
            $table->dropIndex('idx_predictive_etas_shipment_id');
        });

        Schema::table('couriers', function (Blueprint $table) {
            $table->dropIndex('idx_couriers_code');
            $table->dropIndex('idx_couriers_is_active');
            $table->dropIndex('idx_couriers_tenant_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_phone');
            $table->dropIndex('idx_customers_email');
            $table->dropIndex('idx_customers_tenant_id');
        });

        Schema::table('shipment_status_histories', function (Blueprint $table) {
            $table->dropIndex('idx_status_history_shipment_time');
            $table->dropIndex('idx_status_history_happened_at');
            $table->dropIndex('idx_status_history_status');
            $table->dropIndex('idx_status_history_shipment_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_payment_status');
            $table->dropIndex('idx_orders_created_at');
            $table->dropIndex('idx_orders_customer_id');
            $table->dropIndex('idx_orders_order_number');
            $table->dropIndex('idx_orders_external_order_id');
            $table->dropIndex('idx_orders_tenant_status');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('idx_shipments_courier_id');
            $table->dropIndex('idx_shipments_customer_id');
            $table->dropIndex('idx_shipments_created_at');
            $table->dropIndex('idx_shipments_courier_tracking_id');
            $table->dropIndex('idx_shipments_tracking_number');
            $table->dropIndex('idx_shipments_tenant_status');
        });
    }
};
