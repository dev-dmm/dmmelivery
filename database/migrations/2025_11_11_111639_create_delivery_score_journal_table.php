<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_score_journal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('shipment_id');
            $table->smallInteger('delta'); // -1 or +1
            $table->string('reason', 32);  // delivered|returned|cancelled
            $table->timestamp('created_at')->useCurrent();
            
            // Unique constraint: one journal entry per shipment (DB-level protection)
            // This unique index also serves as a regular index for lookups
            $table->unique('shipment_id', 'delivery_score_journal_shipment_uidx');
            
            // Indexes for joins and queries
            $table->index('customer_id', 'dsj_customer_idx');
            $table->index(['customer_id', 'created_at'], 'journal_customer_created_idx');
            
            // Foreign keys for data integrity (with explicit names for easier monitoring)
            // NOTE: CASCADE delete means journal entries are deleted when shipment is deleted.
            // This is intentional: journal entries are tied to specific shipments and lose meaning
            // if the shipment is removed. If you need retention, consider soft deletes on shipments
            // or changing this to SET NULL (requires making shipment_id nullable).
            $table->foreign('shipment_id', 'dsj_shipment_fk')
                ->references('id')
                ->on('shipments')
                ->onDelete('cascade');
            
            $table->foreign('customer_id', 'dsj_customer_fk')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');
        });
        
        // Add CHECK constraint for delta values
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            try {
                DB::statement("
                    ALTER TABLE delivery_score_journal
                    ADD CONSTRAINT chk_journal_delta CHECK (delta IN (-1, 1))
                ");
            } catch (\Throwable $e) {
                // Constraint might already exist, skip
            }
        } elseif ($driver === 'mysql') {
            $version = DB::selectOne('SELECT VERSION() AS v')->v;
            $semver = preg_replace('/[^0-9.].*/', '', $version);
            
            if (version_compare($semver, '8.0.16', '>=')) {
                try {
                    DB::statement("
                        ALTER TABLE delivery_score_journal
                        ADD CONSTRAINT chk_journal_delta CHECK (delta IN (-1, 1))
                    ");
                } catch (\Throwable $e) {
                    // Constraint might already exist, skip
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_score_journal');
    }
};
