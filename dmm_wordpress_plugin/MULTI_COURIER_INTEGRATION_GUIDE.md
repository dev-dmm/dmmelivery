# Multi-Courier Integration Guide

## Overview
This guide shows you how to integrate the multi-courier provider system into your existing DMM Delivery Bridge plugin.

## Files Created
- `includes/Courier/Provider.php` - Provider interface
- `includes/Courier/Registry.php` - Provider registry
- `includes/Courier/MetaMapping.php` - Meta key to courier mapping
- `includes/Courier/AcsProvider.php` - ACS provider implementation
- `includes/Courier/SpeedexProvider.php` - Speedex provider implementation
- `includes/Courier/GenericProvider.php` - Generic provider implementation
- `includes/multi-courier-integration.php` - Integration code

## Integration Steps

### 1. Add Includes to Main Plugin File
Add these lines after line 33 in your main plugin file (`dm-delivery-bridge.php`):

```php
// includes
require_once __DIR__ . '/includes/Courier/Provider.php';
require_once __DIR__ . '/includes/Courier/Registry.php';
require_once __DIR__ . '/includes/Courier/MetaMapping.php';

// default providers
require_once __DIR__ . '/includes/Courier/AcsProvider.php';
require_once __DIR__ . '/includes/Courier/SpeedexProvider.php';
require_once __DIR__ . '/includes/Courier/GenericProvider.php';
```

### 2. Register Providers
Add this to your `init()` method:

```php
add_action('plugins_loaded', function () {
    \DMM\Courier\Registry::register(new \DMM\Courier\AcsProvider());
    \DMM\Courier\Registry::register(new \DMM\Courier\SpeedexProvider());
    \DMM\Courier\Registry::register(new \DMM\Courier\GenericProvider());
    
    // Allow 3rd parties to register more providers
    do_action('dmm_register_courier_providers', \DMM\Courier\Registry::class);
});
```

### 3. Update Detection Pipeline
Replace your existing `maybe_capture_voucher` method with the multi-courier version from `multi-courier-integration.php`.

### 4. Update Worker
Replace your existing worker method with the multi-courier version from `multi-courier-integration.php`.

### 5. Add Settings UI
Add the multi-courier settings to your existing settings method using the code from `multi-courier-integration.php`.

### 6. Update System Status
Add the multi-courier status to your existing system status method using the code from `multi-courier-integration.php`.

## Key Features

### Automatic Courier Detection
- **Meta Key Mapping**: Automatically detects courier from field names
- **Note Detection**: Detects courier hints from order notes
- **Priority Resolution**: Uses priority order for ambiguous formats
- **Fallback**: Uses default courier for unknown keys

### Provider System
- **Pluggable**: Easy to add new couriers
- **Validation**: Courier-specific voucher validation
- **Normalization**: Courier-specific voucher formatting
- **Routing**: Courier-specific API routing

### Settings
- **Default Courier**: Select default for unknown keys
- **Priority Order**: Configure courier priority
- **Meta Key Mapping**: Assign fields to couriers

## Testing

### Test Cases
1. **Meta key mapped to Speedex** → validates via Speedex
2. **Generic key with ACS format** → resolves to ACS (priority respected)
3. **Ambiguous format** → priority order decides
4. **Order note with courier hint** → resolves to correct courier
5. **Cross-order dedupe** → respects courier uniqueness
6. **Missing provider** → logs "no_provider" and skips

### CLI Commands
```bash
# Scan with auto-detection
wp dmm vouchers scan --order=123

# Force specific courier
wp dmm vouchers scan --order=123 --courier=speedex

# Retry failed jobs for specific courier
wp dmm vouchers retry --failed --courier=acs
```

## Benefits

### Backward Compatibility
- Keeps all existing ACS functionality
- No breaking changes to existing code
- Gradual migration path

### Extensibility
- Easy to add new couriers
- Third-party provider support
- Configurable priority system

### Operational Excellence
- Maintains all existing hardening (canary, dedupe, Retry-After, etc.)
- Multi-courier aware monitoring
- Enhanced CLI commands

## Next Steps

1. **Review the integration code** in `multi-courier-integration.php`
2. **Add the includes** to your main plugin file
3. **Register the providers** in your init method
4. **Update your detection pipeline** with the multi-courier version
5. **Test with your existing orders** to ensure compatibility
6. **Add the settings UI** for multi-courier configuration

The system is designed to be a drop-in replacement that enhances your existing functionality without breaking anything.
