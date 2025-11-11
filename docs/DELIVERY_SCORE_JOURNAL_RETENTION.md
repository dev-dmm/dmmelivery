# Delivery Score Journal - Retention Policy

This document explains the retention policy for the `delivery_score_journal` table and how to configure it based on your business needs.

## Overview

The `delivery_score_journal` table maintains an immutable audit trail of delivery score changes. Each journal entry records:
- Which shipment triggered the score change
- Which customer received the score change
- The delta (+1 for delivered, -1 for returned/cancelled)
- The reason (status that triggered the change)
- The timestamp when the change occurred

## Default Behavior (Option A): CASCADE Delete

**Current default:** Journal entries are automatically deleted when the related shipment or customer is deleted.

### Configuration

The base migration (`2025_11_11_111639_create_delivery_score_journal_table.php`) sets:
- `shipment_id` → `ON DELETE CASCADE`
- `customer_id` → `ON DELETE CASCADE` (unless retention migration is applied)

### When to Use

- **Audit trail tied to entities**: Journal entries only make sense when the shipment/customer exists
- **Compliance**: You don't need to retain historical data after entity deletion
- **Simpler schema**: No nullable foreign keys, cleaner queries

### Example

```sql
-- Deleting a shipment automatically deletes its journal entry
DELETE FROM shipments WHERE id = '...';
-- Journal entry is automatically removed via CASCADE
```

## Retention Mode (Option B): SET NULL on Delete

**Alternative:** Journal entries are preserved even when shipments/customers are deleted.

### Configuration

Apply the retention migration:
```bash
php artisan migrate
# This runs: 2025_11_11_121943_make_customer_id_nullable_in_journal_for_retention.php
```

This migration:
- Makes `customer_id` nullable
- Changes FK to `ON DELETE SET NULL`
- Preserves journal entries when customers are deleted

### When to Use

- **Historical reporting**: You need to analyze score changes even after customer deletion
- **Compliance/audit**: Regulatory requirements mandate data retention
- **Analytics**: You want to track score trends over time regardless of entity lifecycle

### Example

```sql
-- Deleting a customer sets customer_id to NULL but preserves the journal entry
DELETE FROM customers WHERE id = '...';
-- Journal entry remains with customer_id = NULL
```

### Shipment Retention

**Note:** The current schema uses `ON DELETE CASCADE` for `shipment_id`. If you need to retain journal entries when shipments are deleted:

1. **Option 1 (Recommended)**: Use soft deletes on shipments
   ```php
   use Illuminate\Database\Eloquent\SoftDeletes;
   
   class Shipment extends Model {
       use SoftDeletes;
   }
   ```
   - Shipments are "deleted" but records remain
   - Journal entries stay linked via FK
   - Queries automatically exclude soft-deleted shipments

2. **Option 2**: Make `shipment_id` nullable + `ON DELETE SET NULL`
   - Requires custom migration (similar to customer retention)
   - Journal entries lose shipment context but remain for reporting
   - Consider if this fits your business logic

## Immutability Guarantees

Regardless of retention policy, the following fields are **always immutable**:
- `id` (primary key) - Never changes after insert
- `delta` - Score change amount (-1 or +1)
- `reason` - Status that triggered the change
- `created_at` - Timestamp of the change

Only `customer_id` and `tenant_id` (if present) can be updated via upsert operations.

## Migration Strategy

### Switching from CASCADE to Retention

1. **Backup first**:
   ```bash
   mysqldump -u user -p database delivery_score_journal > journal_backup.sql
   ```

2. **Apply retention migration**:
   ```bash
   php artisan migrate
   ```

3. **Verify**:
   ```sql
   -- Check that customer_id is nullable
   SHOW COLUMNS FROM delivery_score_journal WHERE Field = 'customer_id';
   -- Should show: Null = YES
   ```

### Switching from Retention to CASCADE

1. **Clean up NULL customer_ids** (if desired):
   ```sql
   DELETE FROM delivery_score_journal WHERE customer_id IS NULL;
   ```

2. **Rollback retention migration**:
   ```bash
   php artisan migrate:rollback --step=1
   ```

3. **Verify**:
   ```sql
   -- Check that customer_id is NOT NULL
   SHOW COLUMNS FROM delivery_score_journal WHERE Field = 'customer_id';
   -- Should show: Null = NO
   ```

## Best Practices

1. **Choose early**: Decide on retention policy before production deployment
2. **Document decision**: Note which policy you're using in your deployment docs
3. **Test migrations**: Always test retention policy changes in staging first
4. **Monitor NULLs**: If using retention, periodically review NULL customer_id entries
5. **Index strategy**: The unique index on `shipment_id` ensures one entry per shipment regardless of retention policy

## Related Files

- **Base migration**: `database/migrations/2025_11_11_111639_create_delivery_score_journal_table.php`
- **Retention migration**: `database/migrations/2025_11_11_121943_make_customer_id_nullable_in_journal_for_retention.php`
- **Immutability migration**: `database/migrations/2025_11_11_124105_ensure_immutable_fields_in_delivery_score_journal.php`
- **Model logic**: `app/Models/Shipment.php::updateCustomerDeliveryScore()`

## Questions?

- **Which policy should I use?** → Default (CASCADE) unless you have specific retention requirements
- **Can I change later?** → Yes, but test migrations carefully and backup first
- **What about shipment retention?** → Use soft deletes on shipments for best results

