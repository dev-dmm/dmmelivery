# Multi-Courier Integration Steps

## Quick Integration Guide

Follow these steps to add multi-courier support to your existing plugin:

### Step 1: Add Includes (after line 33 in dm-delivery-bridge.php)

Add these lines right after your constants:

```php
// Multi-courier includes
require_once __DIR__ . '/includes/Courier/Provider.php';
require_once __DIR__ . '/includes/Courier/Registry.php';
require_once __DIR__ . '/includes/Courier/MetaMapping.php';
require_once __DIR__ . '/includes/Courier/AcsProvider.php';
require_once __DIR__ . '/includes/Courier/SpeedexProvider.php';
require_once __DIR__ . '/includes/Courier/GenericProvider.php';
```

### Step 2: Register Providers (in your init() method)

Add this to your existing init() method:

```php
add_action('plugins_loaded', function () {
    \DMM\Courier\Registry::register(new \DMM\Courier\AcsProvider());
    \DMM\Courier\Registry::register(new \DMM\Courier\SpeedexProvider());
    \DMM\Courier\Registry::register(new \DMM\Courier\GenericProvider());

    // Allow 3rd-parties to register more providers
    do_action('dmm_register_courier_providers', \DMM\Courier\Registry::class);
});
```

### Step 3: Add Meta Mapping Functions

Add these functions to your main plugin file:

```php
function dmm_voucher_meta_map(): array {
    $map = [
        'acs' => ['_acs_voucher','acs_voucher','acs_tracking'],
        'speedex' => ['obs_speedex_courier','obs_speedex_courier_pieces'],
        'generic' => ['voucher_number','tracking_number','shipment_id','_dmm_delivery_shipment_id','courier','shipping_courier'],
    ];
    return apply_filters('dmm_voucher_meta_keys', $map);
}

function dmm_courier_priority(): array {
    $csv = get_option('dmm_courier_priority', 'acs,speedex,generic');
    $order = array_values(array_filter(array_map('trim', explode(',', $csv))));
    return $order ?: ['acs','speedex','generic'];
}
```

### Step 4: Replace Detection Function

Replace your existing `maybe_capture_voucher` function with the multi-courier version from `includes/multi-courier-integration-complete.php` (section 4).

### Step 5: Update Worker

Replace your existing worker function with the multi-courier version from `includes/multi-courier-integration-complete.php` (section 5).

### Step 6: Add Settings UI

Add the multi-courier settings to your existing settings method using the code from `includes/multi-courier-integration-complete.php` (section 6).

### Step 7: Add System Status

Add the multi-courier status to your existing system status method using the code from `includes/multi-courier-integration-complete.php` (section 7).

### Step 8: Update CLI Commands

Add the multi-courier CLI commands using the code from `includes/multi-courier-integration-complete.php` (section 8).

### Step 9: Test

1. Test with existing orders to ensure compatibility
2. Test with new courier types
3. Verify settings UI works
4. Check system status shows providers

## What You Get

- ✅ **Multi-courier detection** from meta keys, notes, and validation
- ✅ **Provider system** with ACS, Speedex, and Generic providers
- ✅ **Settings UI** for default courier and priority
- ✅ **System status** showing provider information
- ✅ **CLI commands** for multi-courier operations
- ✅ **Backward compatibility** with all existing functionality
- ✅ **All your hardening** (canary, dedupe, Retry-After, etc.) remains intact

## Files to Reference

- `includes/multi-courier-integration-complete.php` - Complete integration code
- `includes/Courier/` - Provider system files
- `MULTI_COURIER_INTEGRATION_GUIDE.md` - Detailed guide

That's it! Your plugin now has full multi-courier support.
