# Database Schema Error Fix Guide

## Error Description

**Error:** `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'orek_eshop_tracker.global_customers' doesn't exist`

This error occurs when the Laravel application tries to access a database table that doesn't exist. In this case, the `global_customers` table is missing.

## Root Cause

The Laravel migrations haven't been run, or a specific migration failed. The `global_customers` table should be created by the migration file:
- `database/migrations/2025_11_11_104150_create_global_customers_table.php`

## Solution

### Step 1: Check Migration Status

SSH into your Laravel server and run:

```bash
cd /home/oreksi.gr/dmmelivery
php artisan migrate:status
```

This will show you which migrations have been run and which are pending.

### Step 2: Run Migrations

Run all pending migrations:

```bash
php artisan migrate
```

If you encounter any errors during migration, check:
- Database connection settings in `.env`
- Database user permissions
- Available disk space
- Database server status

### Step 3: Verify Table Creation

After running migrations, verify the table exists:

```bash
php artisan tinker
```

Then in tinker:

```php
DB::table('global_customers')->count();
```

If this returns a number (even 0), the table exists. If you get an error, the table still doesn't exist.

### Step 4: Check for Migration Errors

If migrations fail, check the Laravel logs:

```bash
tail -f storage/logs/laravel.log
```

Common migration issues:
- **Foreign key constraints**: If a migration references a table that doesn't exist yet
- **Column conflicts**: If a column already exists but migration tries to add it
- **Database permissions**: Database user doesn't have CREATE TABLE permissions

### Step 5: Run Specific Migration (if needed)

If you need to run just the `global_customers` migration:

```bash
php artisan migrate --path=database/migrations/2025_11_11_104150_create_global_customers_table.php
```

## Related Tables

The `global_customers` feature requires several related tables. Make sure these migrations are also run:

1. **`global_customers`** (main table)
   - Migration: `2025_11_11_104150_create_global_customers_table.php`

2. **`customers` table update** (adds foreign key)
   - Migration: `2025_11_11_104152_add_global_customer_id_to_customers_table.php`

3. **`shipments` table update** (adds foreign key)
   - Migration: `2025_11_11_104154_add_global_customer_id_to_shipments_table.php`

4. **Indexes and constraints**
   - Migration: `2025_11_11_111635_add_unique_index_to_global_customers_fingerprint.php`
   - Migration: `2025_11_11_112808_make_hashed_fingerprint_not_null_in_global_customers_table.php`

## Prevention

To prevent this issue in the future:

1. **Always run migrations after deployment**:
   ```bash
   php artisan migrate --force
   ```

2. **Add migration check to deployment script**:
   ```bash
   #!/bin/bash
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   ```

3. **Monitor for migration failures**:
   - Set up alerts for migration errors
   - Check migration status regularly
   - Test migrations in staging before production

## Verification

After fixing, test by:

1. **Sending a test order** from WordPress
2. **Check logs** for any database errors
3. **Verify in database** that `global_customers` table exists and has proper structure

## Database Structure

The `global_customers` table should have this structure:

```sql
CREATE TABLE `global_customers` (
  `id` char(36) NOT NULL,
  `primary_email` varchar(255) DEFAULT NULL,
  `primary_phone` varchar(255) DEFAULT NULL,
  `hashed_fingerprint` varchar(64) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `global_customers_fingerprint_unique` (`hashed_fingerprint`),
  KEY `global_customers_hashed_fingerprint_index` (`hashed_fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Troubleshooting

### Issue: Migration says "Nothing to migrate"

**Solution:** Check if the migration was already run but the table was dropped:
```bash
php artisan migrate:rollback --step=1
php artisan migrate
```

### Issue: Foreign key constraint error

**Solution:** The migration order matters. Run migrations in order:
```bash
php artisan migrate:refresh
```

### Issue: Permission denied

**Solution:** Check database user permissions:
```sql
GRANT ALL PRIVILEGES ON orek_eshop_tracker.* TO 'your_user'@'localhost';
FLUSH PRIVILEGES;
```

### Issue: Table exists but migration fails

**Solution:** Check if table structure matches migration:
```bash
php artisan migrate:status
php artisan migrate --pretend  # See what would be run without executing
```

## WordPress Plugin Behavior

After this fix, the WordPress plugin will:

1. **Detect database schema errors** automatically
2. **Show helpful error messages** like:
   > "Database Schema Error: Table 'global_customers' does not exist. Please run Laravel migrations: php artisan migrate"

3. **Log detailed error information** for debugging

## Related Files

- Migration: `database/migrations/2025_11_11_104150_create_global_customers_table.php`
- Service: `app/Services/GlobalCustomerService.php`
- Model: `app/Models/GlobalCustomer.php`
- Controller: `app/Http/Controllers/WooCommerceOrderController.php` (line 320)

