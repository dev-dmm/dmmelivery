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
        Schema::table('shipments', function (Blueprint $table) {
            $table->timestamp('scored_at')->nullable()->after('actual_delivery');
            $table->smallInteger('scored_delta')->nullable()->after('scored_at')->comment('Score delta: +1 for delivered, -1 for returned/cancelled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['scored_at', 'scored_delta']);
        });
    }
};
