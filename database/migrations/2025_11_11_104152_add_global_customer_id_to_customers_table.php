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
            $table->uuid('global_customer_id')->nullable()->after('tenant_id');
            $table->foreign('global_customer_id')->references('id')->on('global_customers')->onDelete('set null');
            $table->index('global_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['global_customer_id']);
            $table->dropIndex(['global_customer_id']);
            $table->dropColumn('global_customer_id');
        });
    }
};
