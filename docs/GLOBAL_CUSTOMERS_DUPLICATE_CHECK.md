# Global Customers - Duplicate Fingerprint Check

Before running the unique index migration, check for existing duplicate fingerprints.

## SQL Query to Find Duplicates

```sql
-- Find duplicate fingerprints
SELECT hashed_fingerprint, COUNT(*) as count
FROM global_customers
WHERE hashed_fingerprint IS NOT NULL
GROUP BY hashed_fingerprint
HAVING COUNT(*) > 1;
```

## Cleanup Script (Tinker)

If duplicates are found, use this script to merge them (keeps the oldest record as canonical):

```php
// Run in tinker: php artisan tinker

$duplicates = \DB::table('global_customers')
    ->select('hashed_fingerprint', \DB::raw('COUNT(*) as count'))
    ->whereNotNull('hashed_fingerprint')
    ->groupBy('hashed_fingerprint')
    ->havingRaw('COUNT(*) > 1')
    ->get();

foreach ($duplicates as $dup) {
    // Get all records with this fingerprint, ordered by created_at
    $records = \App\Models\GlobalCustomer::where('hashed_fingerprint', $dup->hashed_fingerprint)
        ->orderBy('created_at')
        ->get();
    
    if ($records->count() > 1) {
        $canonical = $records->first(); // Keep the oldest
        $others = $records->skip(1);
        
        foreach ($others as $other) {
            // Update all customers linked to the duplicate to point to canonical
            \App\Models\Customer::where('global_customer_id', $other->id)
                ->update(['global_customer_id' => $canonical->id]);
            
            // Update all shipments linked to the duplicate
            \App\Models\Shipment::where('global_customer_id', $other->id)
                ->update(['global_customer_id' => $canonical->id]);
            
            // Delete the duplicate
            $other->delete();
            
            echo "Merged duplicate {$other->id} into canonical {$canonical->id}\n";
        }
    }
}
```

## Run Before Migration

```bash
# 1. Check for duplicates
php artisan tinker
# Run the SQL query above

# 2. If duplicates exist, run cleanup script
# (see above)

# 3. Then run migration
php artisan migrate
```

