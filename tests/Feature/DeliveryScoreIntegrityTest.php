<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Shipment;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Courier;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DeliveryScoreIntegrityTest extends TestCase
{
    protected Tenant $tenant;
    protected Customer $customer;
    protected Courier $courier;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Stabilize time for deterministic tests
        Carbon::setTestNow('2025-11-11 12:00:00');
        
        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'delivery_score' => 0,
        ]);
        $this->courier = Courier::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }
    
    protected function tearDown(): void
    {
        // Reset time after tests
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test that concurrent scoring only happens once (race condition protection)
     */
    public function test_scores_only_once_under_concurrency(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
            'scored_at' => null,
            'scored_delta' => null,
        ]);

        $oldScore = $this->customer->delivery_score;

        // Simulate two concurrent scoring attempts
        // First call should succeed, second should be skipped due to lock + re-check
        $shipment->updateCustomerDeliveryScore('in_transit', 'delivered');
        
        // Refresh shipment to get latest state
        $shipment->refresh();
        
        // Second call should detect scored_at and skip
        $shipment->updateCustomerDeliveryScore('in_transit', 'delivered');

        // Assertions
        $this->assertEquals(1, DB::table('delivery_score_journal')
            ->where('shipment_id', $shipment->id)
            ->count());

        $this->customer->refresh();
        $this->assertEquals($oldScore + 1, $this->customer->delivery_score);

        $shipment->refresh();
        $this->assertNotNull($shipment->scored_at);
        $this->assertEquals(1, $shipment->scored_delta);
    }

    /**
     * Test that CHECK constraint rejects invalid journal reason where enforced
     */
    public function test_rejects_invalid_journal_reason_where_enforced(): void
    {
        $driver = DB::getDriverName();
        
        // Check if CHECK constraints are enforced
        $enforced = false;
        if ($driver === 'pgsql') {
            $enforced = true;
        } elseif ($driver === 'mysql') {
            $version = DB::selectOne('SELECT VERSION() AS v')->v ?? '';
            $isMaria = stripos($version, 'mariadb') !== false;
            $semver = preg_replace('/[^0-9.].*/', '', $version);
            $enforced = !$isMaria && version_compare($semver, '8.0.16', '>=');
        }

        if (!$enforced) {
            $this->markTestSkipped('CHECK constraints not enforced on this database');
        }

        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
        ]);

        // Attempt to insert invalid reason
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        DB::table('delivery_score_journal')->insert([
            'id' => (string) Str::uuid(),
            'shipment_id' => $shipment->id,
            'customer_id' => $this->customer->id,
            'delta' => 1,
            'reason' => 'invalid_reason', // Should be rejected by CHECK constraint
            'created_at' => now(),
        ]);
    }

    /**
     * Test that final-to-final transition does not rescore
     */
    public function test_does_not_rescore_on_final_to_final_transition(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'delivered',
            'scored_at' => now(),
            'scored_delta' => 1,
        ]);

        // Create journal entry for initial scoring
        DB::table('delivery_score_journal')->insert([
            'id' => (string) Str::uuid(),
            'shipment_id' => $shipment->id,
            'customer_id' => $this->customer->id,
            'delta' => 1,
            'reason' => 'delivered',
            'created_at' => now(),
        ]);

        $this->customer->increment('delivery_score', 1);
        $oldScore = $this->customer->delivery_score;

        // Attempt to transition from delivered to cancelled (both final)
        $shipment->updateCustomerDeliveryScore('delivered', 'cancelled');

        // Score should not change
        $this->customer->refresh();
        $this->assertEquals($oldScore, $this->customer->delivery_score);

        // Journal should still have only one entry
        $this->assertEquals(1, DB::table('delivery_score_journal')
            ->where('shipment_id', $shipment->id)
            ->count());
    }

    /**
     * Test that scoring creates journal entry with correct values
     */
    public function test_scoring_creates_journal_entry(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
            'scored_at' => null,
        ]);

        $shipment->updateCustomerDeliveryScore('in_transit', 'delivered');

        $journalEntry = DB::table('delivery_score_journal')
            ->where('shipment_id', $shipment->id)
            ->first();

        $this->assertNotNull($journalEntry);
        $this->assertEquals($this->customer->id, $journalEntry->customer_id);
        $this->assertEquals(1, $journalEntry->delta);
        $this->assertEquals('delivered', $journalEntry->reason);
    }

    /**
     * Test that returned shipment scores -1
     */
    public function test_returned_shipment_scores_negative(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
            'scored_at' => null,
        ]);

        $oldScore = $this->customer->delivery_score;

        $shipment->updateCustomerDeliveryScore('in_transit', 'returned');

        $this->customer->refresh();
        $this->assertEquals($oldScore - 1, $this->customer->delivery_score);

        $journalEntry = DB::table('delivery_score_journal')
            ->where('shipment_id', $shipment->id)
            ->first();

        $this->assertNotNull($journalEntry);
        $this->assertEquals(-1, $journalEntry->delta);
        $this->assertEquals('returned', $journalEntry->reason);
    }

    /**
     * Test that cancelled shipment scores -1
     */
    public function test_cancelled_shipment_scores_negative(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
            'scored_at' => null,
        ]);

        $oldScore = $this->customer->delivery_score;

        $shipment->updateCustomerDeliveryScore('in_transit', 'cancelled');

        $this->customer->refresh();
        $this->assertEquals($oldScore - 1, $this->customer->delivery_score);

        $journalEntry = DB::table('delivery_score_journal')
            ->where('shipment_id', $shipment->id)
            ->first();

        $this->assertNotNull($journalEntry);
        $this->assertEquals(-1, $journalEntry->delta);
        $this->assertEquals('cancelled', $journalEntry->reason);
    }

    /**
     * Test that deleted customer mid-transaction is handled gracefully
     */
    public function test_handles_deleted_customer_mid_transaction(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
            'scored_at' => null,
        ]);

        // Delete customer before scoring attempt
        $customerId = $this->customer->id;
        $this->customer->delete();

        // Refresh shipment to get updated customer_id (should be null if FK is SET NULL)
        $shipment->refresh();
        
        // Attempt to score - should handle gracefully
        $shipment->updateCustomerDeliveryScore('in_transit', 'delivered');
        
        // Should not create journal entry
        $journalEntry = DB::table('delivery_score_journal')
            ->where('shipment_id', $shipment->id)
            ->first();
        
        $this->assertNull($journalEntry);
    }

    /**
     * Test that double status update in same tick only scores once
     */
    public function test_double_status_update_same_tick_only_scores_once(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
            'scored_at' => null,
        ]);

        $oldScore = $this->customer->delivery_score;

        // Simulate two parallel calls (same tick)
        $shipment->updateCustomerDeliveryScore('in_transit', 'delivered');
        $shipment->updateCustomerDeliveryScore('in_transit', 'delivered');

        // Should have only one journal entry
        $journalCount = DB::table('delivery_score_journal')
            ->where('shipment_id', $shipment->id)
            ->count();
        
        $this->assertEquals(1, $journalCount);

        // Score should have increased by only 1
        $this->customer->refresh();
        $this->assertEquals($oldScore + 1, $this->customer->delivery_score);

        // Shipment should be marked as scored
        $shipment->refresh();
        $this->assertNotNull($shipment->scored_at);
        $this->assertEquals(1, $shipment->scored_delta);
    }

    /**
     * Test that journal retention works when customer is deleted (if SET NULL FK is enabled)
     * This test verifies the optional retention migration behavior.
     */
    public function test_journal_retention_on_customer_delete(): void
    {
        // Skip if retention migration hasn't been run (customer_id is NOT NULL with CASCADE)
        $driver = DB::getDriverName();
        $columnInfo = null;
        
        if ($driver === 'mysql') {
            $columnInfo = DB::selectOne("
                SELECT IS_NULLABLE, COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'delivery_score_journal'
                  AND COLUMN_NAME = 'customer_id'
            ");
        } elseif ($driver === 'pgsql') {
            $columnInfo = DB::selectOne("
                SELECT is_nullable, data_type
                FROM information_schema.columns
                WHERE table_name = 'delivery_score_journal'
                  AND column_name = 'customer_id'
                  AND table_schema = current_schema()
            ");
        }
        
        $isNullable = false;
        if ($columnInfo) {
            if ($driver === 'mysql') {
                $isNullable = ($columnInfo->IS_NULLABLE ?? 'NO') === 'YES';
            } elseif ($driver === 'pgsql') {
                $isNullable = ($columnInfo->is_nullable ?? 'NO') === 'YES';
            }
        }
        
        if (!$isNullable) {
            $this->markTestSkipped('Journal retention migration not applied (customer_id is NOT NULL)');
        }
        
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
            'scored_at' => null,
        ]);

        // Score the shipment first
        $shipment->updateCustomerDeliveryScore('in_transit', 'delivered');
        
        // Verify journal entry exists with customer_id
        $journalEntry = DB::table('delivery_score_journal')
            ->where('shipment_id', $shipment->id)
            ->first();
        
        $this->assertNotNull($journalEntry);
        $this->assertEquals($this->customer->id, $journalEntry->customer_id);
        
        // Delete customer
        $customerId = $this->customer->id;
        $this->customer->delete();
        
        // Verify journal entry still exists but customer_id is NULL
        $journalEntryAfter = DB::table('delivery_score_journal')
            ->where('shipment_id', $shipment->id)
            ->first();
        
        $this->assertNotNull($journalEntryAfter, 'Journal entry should be preserved after customer deletion');
        $this->assertNull($journalEntryAfter->customer_id, 'customer_id should be NULL after customer deletion');
        $this->assertEquals($journalEntry->delta, $journalEntryAfter->delta, 'Delta should remain unchanged');
        $this->assertEquals($journalEntry->reason, $journalEntryAfter->reason, 'Reason should remain unchanged');
    }

    /**
     * Test that journal primary key does not change on upsert
     * This ensures immutable audit trail - PK should never change after initial insert
     * 
     * This test directly tests the upsert behavior without calling updateCustomerDeliveryScore
     * to avoid any side effects from model events
     */
    public function test_journal_primary_key_does_not_change_on_rescore_attempt(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
        ]);

        // Create initial journal entry manually (simulating first score)
        $originalId = (string) Str::uuid();
        $originalDelta = 1;
        $originalReason = 'delivered';
        $originalCreatedAt = now();
        
        $initialPayload = [
            'id' => $originalId,
            'shipment_id' => $shipment->id,
            'customer_id' => $this->customer->id,
            'delta' => $originalDelta,
            'reason' => $originalReason,
            'created_at' => $originalCreatedAt,
        ];
        
        if (Schema::hasColumn('delivery_score_journal', 'tenant_id')) {
            $initialPayload['tenant_id'] = $shipment->tenant_id;
        }
        
        DB::table('delivery_score_journal')->insert($initialPayload);
        
        $first = DB::table('delivery_score_journal')->where('shipment_id', $shipment->id)->first();
        $this->assertNotNull($first);
        $this->assertEquals($originalId, $first->id);

        // Act: simulate upsert scenario - try to insert/update with same shipment_id
        // This tests that upsert doesn't change the PK
        $newId = (string) Str::uuid();
        $updateColumns = ['customer_id'];
        if (Schema::hasColumn('delivery_score_journal', 'tenant_id')) {
            $updateColumns[] = 'tenant_id';
        }
        
        $upsertPayload = [
            'shipment_id' => $shipment->id,
            'id' => $newId, // This should be ignored on update
            'customer_id' => $this->customer->id,
            'delta' => 999, // This should be ignored on update
            'reason' => 'changed', // This should be ignored on update
            'created_at' => now(), // This should be ignored on update
        ];
        
        if (Schema::hasColumn('delivery_score_journal', 'tenant_id')) {
            $upsertPayload['tenant_id'] = $shipment->tenant_id;
        }
        
        // This upsert should update only customer_id (and tenant_id if exists), not id/delta/reason/created_at
        DB::table('delivery_score_journal')->upsert(
            [$upsertPayload],
            ['shipment_id'],
            $updateColumns
        );
        
        $second = DB::table('delivery_score_journal')->where('shipment_id', $shipment->id)->first();

        // Assert: same PK id (immutable audit trail)
        $this->assertEquals($originalId, $second->id, 'Journal primary key should not change on upsert');
        
        // Also verify other immutable fields remain unchanged
        $this->assertEquals($originalDelta, $second->delta, 'Delta should remain unchanged');
        $this->assertEquals($originalReason, $second->reason, 'Reason should remain unchanged');
        $this->assertEquals($originalCreatedAt, $second->created_at, 'created_at should remain unchanged');
        
        // Verify that customer_id was updated (the only mutable field)
        $this->assertEquals($this->customer->id, $second->customer_id, 'customer_id should be updated');
    }

    /**
     * Test that immutable fields (delta, reason, created_at) are truly immutable
     * This verifies that even if someone tries to update these fields directly,
     * they remain unchanged (application-level protection via upsert update list)
     */
    public function test_immutable_fields_cannot_be_updated_via_upsert(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
        ]);

        // Create initial journal entry
        $originalId = (string) Str::uuid();
        $originalDelta = 1;
        $originalReason = 'delivered';
        $originalCreatedAt = now();
        
        $initialPayload = [
            'id' => $originalId,
            'shipment_id' => $shipment->id,
            'customer_id' => $this->customer->id,
            'delta' => $originalDelta,
            'reason' => $originalReason,
            'created_at' => $originalCreatedAt,
        ];
        
        if (Schema::hasColumn('delivery_score_journal', 'tenant_id')) {
            $initialPayload['tenant_id'] = $shipment->tenant_id;
        }
        
        DB::table('delivery_score_journal')->insert($initialPayload);
        
        // Try to upsert with different immutable values
        // These should be completely ignored because they're not in the update list
        $upsertPayload = [
            'shipment_id' => $shipment->id,
            'id' => (string) Str::uuid(), // Different ID - should be ignored
            'customer_id' => $this->customer->id,
            'delta' => 999, // Different delta - should be ignored
            'reason' => 'cancelled', // Different reason - should be ignored
            'created_at' => now()->addDays(10), // Different timestamp - should be ignored
        ];
        
        if (Schema::hasColumn('delivery_score_journal', 'tenant_id')) {
            $upsertPayload['tenant_id'] = $shipment->tenant_id;
        }
        
        $updateColumns = ['customer_id'];
        if (Schema::hasColumn('delivery_score_journal', 'tenant_id')) {
            $updateColumns[] = 'tenant_id';
        }
        
        // Upsert with only customer_id in update list (immutable fields excluded)
        DB::table('delivery_score_journal')->upsert(
            [$upsertPayload],
            ['shipment_id'],
            $updateColumns
        );
        
        $result = DB::table('delivery_score_journal')->where('shipment_id', $shipment->id)->first();
        
        // Assert all immutable fields remain unchanged
        $this->assertEquals($originalId, $result->id, 'Primary key id must remain unchanged');
        $this->assertEquals($originalDelta, $result->delta, 'Delta must remain unchanged (immutable)');
        $this->assertEquals($originalReason, $result->reason, 'Reason must remain unchanged (immutable)');
        $this->assertEquals($originalCreatedAt, $result->created_at, 'created_at must remain unchanged (immutable)');
    }

    /**
     * Test that unique index on shipment_id is required for upsert to work correctly
     * This is a structural integrity test - verifies the database constraint exists
     * 
     * NOTE: This test verifies the index exists by column name (more robust than checking
     * specific index name, which may vary across DB drivers)
     */
    public function test_unique_index_exists_on_shipment_id(): void
    {
        $driver = DB::getDriverName();
        $uniqueExists = false;
        
        if ($driver === 'mysql') {
            // Check for unique index on shipment_id column (by column, not by name)
            $result = DB::selectOne("
                SELECT COUNT(*) as c FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'delivery_score_journal'
                  AND COLUMN_NAME = 'shipment_id'
                  AND NON_UNIQUE = 0
            ");
            $uniqueExists = ($result->c ?? 0) > 0;
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Check for unique constraint/index on shipment_id
            // More robust: check index definition for UNIQUE + shipment_id
            $result = DB::selectOne("
                SELECT COUNT(*) as c
                FROM pg_indexes i
                JOIN pg_constraint c ON c.conname = i.indexname
                WHERE i.tablename = 'delivery_score_journal'
                  AND c.contype = 'u'
                  AND EXISTS (
                      SELECT 1 FROM pg_attribute a
                      JOIN pg_constraint c2 ON c2.conrelid = a.attrelid
                      WHERE a.attrelid = (
                          SELECT oid FROM pg_class WHERE relname = 'delivery_score_journal'
                      )
                      AND a.attname = 'shipment_id'
                      AND c2.conname = c.conname
                  )
            ");
            $uniqueExists = ($result->c ?? 0) > 0;
            
            // Alternative check: verify index definition contains UNIQUE and shipment_id
            if (!$uniqueExists) {
                $indexDef = DB::selectOne("
                    SELECT pg_get_indexdef(i.oid) as indexdef
                    FROM pg_indexes idx
                    JOIN pg_class t ON t.relname = idx.tablename
                    JOIN pg_index i ON i.indexrelid = (
                        SELECT oid FROM pg_class WHERE relname = idx.indexname
                    )
                    WHERE idx.tablename = 'delivery_score_journal'
                      AND idx.indexname LIKE '%shipment%'
                ");
                
                if ($indexDef && isset($indexDef->indexdef)) {
                    $def = strtolower($indexDef->indexdef);
                    $uniqueExists = strpos($def, 'unique') !== false && strpos($def, 'shipment_id') !== false;
                }
            }
        } elseif ($driver === 'sqlite') {
            // SQLite: Check for unique index on shipment_id
            $result = DB::selectOne("
                SELECT COUNT(*) as c FROM sqlite_master
                WHERE type = 'index'
                  AND tbl_name = 'delivery_score_journal'
                  AND sql LIKE '%UNIQUE%'
                  AND sql LIKE '%shipment_id%'
            ");
            $uniqueExists = ($result->c ?? 0) > 0;
        }
        
        $this->assertTrue(
            $uniqueExists,
            'Unique index on shipment_id column must exist for upsert to work correctly'
        );
    }

    /**
     * Test that created_at column does not have ON UPDATE CURRENT_TIMESTAMP
     * This ensures created_at remains immutable (never auto-updates)
     */
    public function test_created_at_has_no_on_update_timestamp(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            $result = DB::selectOne("
                SELECT EXTRA
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'delivery_score_journal'
                  AND COLUMN_NAME = 'created_at'
            ");
            
            $extra = $result->EXTRA ?? '';
            // EXTRA should be empty or contain 'DEFAULT_GENERATED', but NOT 'on update current_timestamp'
            $hasOnUpdate = stripos($extra, 'on update') !== false;
            
            $this->assertFalse(
                $hasOnUpdate,
                'created_at column must NOT have ON UPDATE CURRENT_TIMESTAMP (must be immutable)'
            );
        } else {
            // PostgreSQL and SQLite don't have ON UPDATE CURRENT_TIMESTAMP feature
            // This test is MySQL-specific
            $this->markTestSkipped('ON UPDATE CURRENT_TIMESTAMP is MySQL-specific');
        }
    }

    /**
     * Test that database triggers prevent direct updates to immutable fields
     * This verifies DB-level protection (MySQL trigger or PostgreSQL trigger)
     */
    public function test_database_trigger_prevents_immutable_field_updates(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
            'scored_at' => null,
        ]);

        // Create journal entry
        $shipment->updateCustomerDeliveryScore('in_transit', 'delivered');
        
        $journalEntry = DB::table('delivery_score_journal')
            ->where('shipment_id', $shipment->id)
            ->first();
        
        $this->assertNotNull($journalEntry);
        $originalDelta = $journalEntry->delta;
        $originalReason = $journalEntry->reason;
        $originalCreatedAt = $journalEntry->created_at;
        $originalId = $journalEntry->id;

        $driver = DB::getDriverName();
        
        // Test that direct UPDATE is blocked by trigger
        if ($driver === 'mysql') {
            // MySQL trigger should block updates
            $this->expectException(\Illuminate\Database\QueryException::class);
            DB::statement("
                UPDATE delivery_score_journal
                SET delta = 999
                WHERE shipment_id = ?
            ", [$shipment->id]);
        } elseif ($driver === 'pgsql') {
            // PostgreSQL trigger should block updates
            $this->expectException(\Illuminate\Database\QueryException::class);
            DB::statement("
                UPDATE delivery_score_journal
                SET delta = 999
                WHERE shipment_id = ?
            ", [$shipment->id]);
        } else {
            // SQLite doesn't support triggers that block updates
            $this->markTestSkipped('Database trigger test requires MySQL or PostgreSQL');
        }
    }

    /**
     * Test that journal created_at and shipment scored_at are approximately synchronized
     * This ensures time source consistency (tolerance: 2 seconds)
     */
    public function test_journal_created_at_synchronized_with_shipment_scored_at(): void
    {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $shipment = Shipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'status' => 'in_transit',
            'scored_at' => null,
        ]);

        // Score the shipment
        $shipment->updateCustomerDeliveryScore('in_transit', 'delivered');
        
        // Refresh to get updated scored_at
        $shipment->refresh();
        $journalEntry = DB::table('delivery_score_journal')
            ->where('shipment_id', $shipment->id)
            ->first();
        
        $this->assertNotNull($journalEntry);
        $this->assertNotNull($shipment->scored_at);
        
        // Convert to Carbon for comparison
        $scoredAt = \Carbon\Carbon::parse($shipment->scored_at);
        $createdAt = \Carbon\Carbon::parse($journalEntry->created_at);
        
        // Assert timestamps are within 2 seconds of each other
        $diff = abs($scoredAt->diffInSeconds($createdAt));
        $this->assertLessThanOrEqual(
            2,
            $diff,
            "Journal created_at and shipment scored_at should be synchronized (diff: {$diff}s)"
        );
    }
}

