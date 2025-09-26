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
        Schema::table('customers', function (Blueprint $table) {
            // Add unique constraint for email within tenant
            $table->unique(['tenant_id', 'email'], 'customers_tenant_email_unique');
            
            // Add unique constraint for phone within tenant (only if phone is not null)
            // Note: This will only apply to non-null phone numbers
            $table->unique(['tenant_id', 'phone'], 'customers_tenant_phone_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_tenant_email_unique');
            $table->dropUnique('customers_tenant_phone_unique');
        });
    }
};
