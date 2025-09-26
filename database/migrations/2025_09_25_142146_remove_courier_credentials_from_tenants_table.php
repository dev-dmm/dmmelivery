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
            // Remove ACS courier credential columns (only these were actually added)
            $table->dropColumn([
                'acs_api_key',
                'acs_company_id', 
                'acs_company_password',
                'acs_user_id',
                'acs_user_password',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Re-add the columns if needed to rollback
            $table->string('acs_api_key')->nullable();
            $table->string('acs_company_id')->nullable();
            $table->string('acs_company_password')->nullable();
            $table->string('acs_user_id')->nullable();
            $table->string('acs_user_password')->nullable();
        });
    }
};
