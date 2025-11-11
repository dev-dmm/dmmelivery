# How to Get Courier Information from an Order

## Overview

After removing the default courier logic, courier information for orders is determined through a priority system. This document explains how to access courier information.

## Storage Locations

Courier information can be stored in multiple places (in priority order):

1. **Shipment's Courier** (Highest Priority)
   - When a shipment is created, it has a `courier_id`
   - This is the most reliable source

2. **Order's Additional Data** (Voucher Information)
   - `order.additional_data['courier_company']` - Courier name from voucher
   - `order.additional_data['voucher_number']` - Voucher/tracking number

3. **Order's Preferred Courier Field** (Legacy)
   - `order.preferred_courier` - Legacy field, still supported

## Helper Methods

The `Order` model now includes helper methods to easily get courier information:

### 1. `getCourier(): ?Courier`

Returns the Courier model instance for this order, or `null` if not found.

**Priority:**
1. Courier from shipment (if shipment exists)
2. Courier from voucher (`additional_data.courier_company`)
3. Preferred courier field (legacy)

**Example:**
```php
$order = Order::find($orderId);
$courier = $order->getCourier();

if ($courier) {
    echo $courier->name; // "ACS", "Geniki", "ELTA", etc.
    echo $courier->code; // "ACS", "GEN", "ELT", etc.
}
```

### 2. `getCourierName(): string`

Returns the courier name as a string for display purposes.

**Returns:**
- Courier name if found (e.g., "ACS", "Geniki Taxidromiki")
- "Not assigned" if no courier found

**Example:**
```php
$order = Order::find($orderId);
echo $order->getCourierName(); // "ACS" or "Not assigned"
```

### 3. `getVoucherNumber(): ?string`

Returns the voucher/tracking number for this order.

**Priority:**
1. Shipment tracking number (if it's a voucher, not generated)
2. `additional_data['voucher_number']`

**Example:**
```php
$order = Order::find($orderId);
$voucher = $order->getVoucherNumber();

if ($voucher) {
    echo "Voucher: " . $voucher; // "ACS123456"
}
```

### 4. `hasVoucher(): bool`

Checks if the order has a voucher number.

**Example:**
```php
$order = Order::find($orderId);
if ($order->hasVoucher()) {
    echo "Order has voucher: " . $order->getVoucherNumber();
}
```

## Usage Examples

### In Controllers

```php
$order = Order::with(['shipments.courier'])->find($orderId);

// Get courier
$courier = $order->getCourier();
$courierName = $order->getCourierName();
$voucherNumber = $order->getVoucherNumber();

return response()->json([
    'order_id' => $order->id,
    'courier' => $courier ? [
        'id' => $courier->id,
        'name' => $courier->name,
        'code' => $courier->code,
    ] : null,
    'courier_name' => $courierName,
    'voucher_number' => $voucherNumber,
    'has_voucher' => $order->hasVoucher(),
]);
```

### In Blade/Views

```php
@if($order->hasVoucher())
    <div>
        <strong>Courier:</strong> {{ $order->getCourierName() }}
        <br>
        <strong>Voucher:</strong> {{ $order->getVoucherNumber() }}
    </div>
@else
    <div>Waiting for voucher...</div>
@endif
```

### In API Resources

```php
class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'courier' => $this->getCourier() ? [
                'id' => $this->getCourier()->id,
                'name' => $this->getCourier()->name,
            ] : null,
            'courier_name' => $this->getCourierName(),
            'voucher_number' => $this->getVoucherNumber(),
            'has_voucher' => $this->hasVoucher(),
        ];
    }
}
```

## Direct Access (Not Recommended)

You can also access the data directly, but using helper methods is recommended:

```php
// Direct access (not recommended)
$courierCompany = $order->additional_data['courier_company'] ?? null;
$voucherNumber = $order->additional_data['voucher_number'] ?? null;
$preferredCourier = $order->preferred_courier;

// Through shipment
$shipment = $order->primaryShipment ?? $order->shipments()->first();
$courier = $shipment?->courier;
```

## When Courier Information is Available

### Order with Voucher (Shipment Created)
- ✅ `getCourier()` returns Courier model
- ✅ `getCourierName()` returns courier name
- ✅ `getVoucherNumber()` returns voucher number
- ✅ `hasVoucher()` returns true

### Order with Voucher (No Shipment Yet)
- ⚠️ `getCourier()` may return Courier if found in database
- ✅ `getCourierName()` returns courier name from voucher
- ✅ `getVoucherNumber()` returns voucher number
- ✅ `hasVoucher()` returns true

### Order without Voucher
- ❌ `getCourier()` returns null
- ⚠️ `getCourierName()` returns "Not assigned"
- ❌ `getVoucherNumber()` returns null
- ❌ `hasVoucher()` returns false

## Summary

**Always use the helper methods:**
- `$order->getCourier()` - Get Courier model
- `$order->getCourierName()` - Get courier name string
- `$order->getVoucherNumber()` - Get voucher number
- `$order->hasVoucher()` - Check if voucher exists

These methods handle all the priority logic and edge cases automatically!

