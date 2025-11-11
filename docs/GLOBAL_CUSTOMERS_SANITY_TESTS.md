# Global Customers System - Sanity Tests

Quick sanity checks to verify the global customers system is working correctly.

## Run in Tinker

```bash
php artisan tinker
```

## Test 1: Empty Identifiers Protection

Verify that the system throws an exception when both email and phone are empty:

```php
$svc = app(\App\Services\GlobalCustomerService::class);

try { 
    $svc->findOrCreateGlobalCustomer(null, null); 
} catch (\InvalidArgumentException $e) { 
    echo '✅ OK - Exception thrown for empty identifiers';
}
```

## Test 2: Fingerprint Consistency

Verify that the same email/phone combination produces the same fingerprint regardless of formatting:

```php
$svc = app(\App\Services\GlobalCustomerService::class);

$a = $svc->generateFingerprint('USER@MAIL.COM', '00 30-69 12 34 56 78');
$b = $svc->generateFingerprint('user@mail.com', '00306912345678');

echo $a === $b ? '✅ OK - Same fingerprint for same data' : '❌ FAIL - Different fingerprints';
```

## Test 3: Scoring Logic

Verify that scoring works correctly:

```php
// Create a test customer
$customer = \App\Models\Customer::factory()->create(['delivery_score' => 0]);

// Create a shipment in transit
$shipment = \App\Models\Shipment::factory()->create([
    'status' => 'in_transit',
    'customer_id' => $customer->id
]);

$oldScore = $customer->delivery_score;

// Change to delivered (should +1)
$shipment->status = 'delivered';
$shipment->save();

$customer->refresh();
echo ($customer->delivery_score === $oldScore + 1) ? '✅ OK - Score incremented' : '❌ FAIL - Score not incremented';

// Try to change to delivered again (should not change - final→final)
$oldScore = $customer->delivery_score;
$shipment->status = 'delivered';
$shipment->save();

$customer->refresh();
echo ($customer->delivery_score === $oldScore) ? '✅ OK - No double scoring' : '❌ FAIL - Double scoring occurred';
```

## Test 4: Global Customer Linking

Verify that customers with same email/phone link to same global customer:

```php
$svc = app(\App\Services\GlobalCustomerService::class);

$gc1 = $svc->findOrCreateGlobalCustomer('test@example.com', '1234567890');
$gc2 = $svc->findOrCreateGlobalCustomer('test@example.com', '1234567890');

echo ($gc1->id === $gc2->id) ? '✅ OK - Same global customer' : '❌ FAIL - Different global customers';
```

## Test 5: Journal Recording

Verify that score changes are recorded in the journal:

```php
$customer = \App\Models\Customer::factory()->create(['delivery_score' => 0]);
$shipment = \App\Models\Shipment::factory()->create([
    'status' => 'in_transit',
    'customer_id' => $customer->id
]);

$journalCountBefore = \DB::table('delivery_score_journal')->where('customer_id', $customer->id)->count();

$shipment->status = 'delivered';
$shipment->save();

$journalCountAfter = \DB::table('delivery_score_journal')->where('customer_id', $customer->id)->count();

echo ($journalCountAfter === $journalCountBefore + 1) ? '✅ OK - Journal entry created' : '❌ FAIL - Journal entry missing';
```

## Test 6: Final→Final Status Change (No Double Journal)

Verify that final→final status changes don't create duplicate journal entries:

```php
$customer = \App\Models\Customer::factory()->create(['delivery_score' => 0]);
$shipment = \App\Models\Shipment::factory()->create([
    'status' => 'in_transit',
    'customer_id' => $customer->id
]);

// Change to delivered (should +1 and create journal entry)
$shipment->update(['status' => 'delivered']);
$firstCount = \DB::table('delivery_score_journal')->where('shipment_id', $shipment->id)->count();

// Change to returned (final→final, should NOT create new journal entry)
$shipment->update(['status' => 'returned']);
$secondCount = \DB::table('delivery_score_journal')->where('shipment_id', $shipment->id)->count();

echo ($firstCount === 1 && $secondCount === 1) ? '✅ OK - No double journal' : '❌ FAIL - Double journal entry created';
```

