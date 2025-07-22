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
        Schema::table('tenants', function (Blueprint $table) {
            // Business Information
            $table->string('business_type')->nullable()->after('name'); // 'eshop', 'marketplace', 'retail', etc.
            $table->text('description')->nullable()->after('business_type');
            $table->string('website_url')->nullable()->after('description');
            $table->string('primary_domain')->nullable()->after('subdomain');
            
            // Contact Information
            $table->string('contact_name')->nullable()->after('website_url');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone')->nullable()->after('contact_email');
            
            // Business Address
            $table->text('business_address')->nullable()->after('contact_phone');
            $table->string('city')->nullable()->after('business_address');
            $table->string('postal_code')->nullable()->after('city');
            $table->string('country', 2)->default('GR')->after('postal_code');
            
            // Tax Information
            $table->string('vat_number')->nullable()->after('country');
            $table->string('tax_office')->nullable()->after('vat_number');
            
            // ACS API Credentials
            $table->string('acs_api_key')->nullable()->after('tax_office');
            $table->string('acs_company_id')->nullable()->after('acs_api_key');
            $table->string('acs_company_password')->nullable()->after('acs_company_id');
            $table->string('acs_user_id')->nullable()->after('acs_company_password');
            $table->string('acs_user_password')->nullable()->after('acs_user_id');
            
            // Other Courier API Keys
            $table->json('courier_api_keys')->nullable()->after('acs_user_password'); // For ELTA, Speedex, etc.
            
            // Onboarding & Status
            $table->enum('onboarding_status', [
                'pending',           // Just registered, email verification needed
                'email_verified',    // Email verified, needs to complete profile
                'profile_completed', // Profile completed, needs payment setup
                'payment_setup',     // Payment setup done, needs API configuration  
                'api_configured',    // API configured, ready for testing
                'testing',           // In testing phase
                'active',           // Fully active and operational
                'suspended',        // Temporarily suspended
                'cancelled'         // Account cancelled
            ])->default('pending')->after('courier_api_keys');
            
            $table->timestamp('onboarding_started_at')->nullable()->after('onboarding_status');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_started_at');
            $table->timestamp('email_verified_at')->nullable()->after('onboarding_completed_at');
            
            // Subscription & Billing
            $table->enum('subscription_plan', [
                'free',     // Free tier with limited shipments
                'starter',  // Small businesses
                'business', // Medium businesses  
                'enterprise' // Large businesses
            ])->default('free')->after('email_verified_at');
            
            $table->integer('monthly_shipment_limit')->default(100)->after('subscription_plan');
            $table->integer('current_month_shipments')->default(0)->after('monthly_shipment_limit');
            $table->date('billing_cycle_start')->nullable()->after('current_month_shipments');
            
            // Feature Flags
            $table->json('enabled_features')->nullable()->after('billing_cycle_start'); // Which features are enabled
            
            // Branding Customization (extend existing)
            $table->json('theme_config')->nullable()->after('enabled_features'); // Colors, fonts, etc.
            $table->string('favicon_url')->nullable()->after('theme_config');
            
            // API & Webhook Settings
            $table->json('webhook_urls')->nullable()->after('favicon_url'); // Customer webhook endpoints
            $table->string('api_token')->nullable()->after('webhook_urls'); // For customer API access
            $table->timestamp('api_token_expires_at')->nullable()->after('api_token');
            
            // Analytics & Tracking
            $table->json('integration_settings')->nullable()->after('api_token_expires_at'); // Google Analytics, etc.
            
            // Notes and Internal Use
            $table->text('admin_notes')->nullable()->after('integration_settings'); // Internal admin notes
            $table->json('onboarding_data')->nullable()->after('admin_notes'); // Store onboarding progress data
            
            // Indexes for performance
            $table->index(['onboarding_status']);
            $table->index(['subscription_plan']);
            $table->index(['primary_domain']);
            $table->index(['contact_email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'business_type', 'description', 'website_url', 'primary_domain',
                'contact_name', 'contact_email', 'contact_phone',
                'business_address', 'city', 'postal_code', 'country',
                'vat_number', 'tax_office',
                'acs_api_key', 'acs_company_id', 'acs_company_password', 'acs_user_id', 'acs_user_password',
                'courier_api_keys',
                'onboarding_status', 'onboarding_started_at', 'onboarding_completed_at', 'email_verified_at',
                'subscription_plan', 'monthly_shipment_limit', 'current_month_shipments', 'billing_cycle_start',
                'enabled_features', 'theme_config', 'favicon_url',
                'webhook_urls', 'api_token', 'api_token_expires_at',
                'integration_settings', 'admin_notes', 'onboarding_data'
            ]);
        });
    }
};
