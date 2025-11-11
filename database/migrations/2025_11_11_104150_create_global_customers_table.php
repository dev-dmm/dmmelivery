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
        Schema::create('global_customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('primary_email')->nullable();
            $table->string('primary_phone')->nullable();
            $table->string('hashed_fingerprint')->unique();
            $table->timestamps();

            $table->index('hashed_fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_customers');
    }
};
