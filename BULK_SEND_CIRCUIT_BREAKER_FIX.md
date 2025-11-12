# Bulk Send Circuit Breaker Protection

## Problem

When the circuit breaker is open (due to high error rate), bulk send operations would still attempt to process orders, resulting in all orders failing with the error:

> "API calls are temporarily disabled due to high error rate."

This created unnecessary error logs and wasted processing time.

## Solution

Added circuit breaker status checks **before** starting bulk operations:

1. **Bulk Send** (`ajax_bulk_send_orders`)
2. **Bulk Sync** (`ajax_bulk_sync_orders`)

### Implementation

Both handlers now check the circuit breaker status at the very beginning, before:
- Querying for orders
- Creating job IDs
- Starting any processing

If the circuit breaker is open, the operation is immediately rejected with a helpful error message that includes:
- ⚠️ Clear warning that bulk operation cannot start
- Time remaining until auto-reset
- Direct link to the Monitoring page for error analysis

### Error Message Format

```
⚠️ Cannot start bulk send: API calls are temporarily disabled due to high error rate. 
The circuit breaker will auto-reset in X minute(s) and Y second(s). 
Please check the Monitoring page to analyze errors and resolve the issue before retrying.
```

The error response includes:
- `circuit_breaker_open: true` - Flag for frontend handling
- `time_remaining: <seconds>` - Time until auto-reset
- `monitoring_url: <url>` - Direct link to monitoring page

## Benefits

1. **Prevents Wasted Processing**: No orders are processed when the circuit breaker is open
2. **Clear User Feedback**: Users immediately know why the operation failed
3. **Actionable Guidance**: Direct link to monitoring page for error analysis
4. **Time Information**: Users know when they can retry

## User Workflow

### Before Fix
1. User clicks "Bulk Send"
2. Operation starts processing orders
3. All orders fail with circuit breaker error
4. User sees many error logs
5. User must manually check monitoring page

### After Fix
1. User clicks "Bulk Send"
2. **Immediate error message** if circuit breaker is open
3. Error message includes:
   - Why it failed
   - When it will auto-reset
   - Link to monitoring page
4. User can:
   - Wait for auto-reset
   - Go to monitoring page to analyze errors
   - Fix root cause and manually reset circuit breaker

## Code Changes

### Files Modified

1. **`dmm_wordpress_plugin/includes/class-dmm-ajax-handlers.php`**
   - Added circuit breaker check in `ajax_bulk_send_orders()`
   - Added circuit breaker check in `ajax_bulk_sync_orders()`

### Code Location

```php
// In ajax_bulk_send_orders() and ajax_bulk_sync_orders()
// Check circuit breaker status before starting bulk operation
$api_client = $this->plugin ? $this->plugin->api_client : null;
if ($api_client && method_exists($api_client, 'get_circuit_breaker_status')) {
    $circuit_breaker_status = $api_client->get_circuit_breaker_status();
    if ($circuit_breaker_status['is_open']) {
        // Return error with helpful message
        wp_send_json_error([...]);
    }
}
```

## Testing

To test this fix:

1. **Simulate Circuit Breaker Open**:
   - Manually open circuit breaker (or wait for it to open due to errors)
   - Attempt bulk send operation
   - Should see immediate error message

2. **Verify Error Message**:
   - Check that error message includes time remaining
   - Verify link to monitoring page works
   - Confirm operation doesn't start processing

3. **Test After Reset**:
   - Reset circuit breaker (manually or wait for auto-reset)
   - Attempt bulk send operation
   - Should proceed normally

## Related Features

This fix works in conjunction with:

- **Circuit Breaker Diagnostics** (see `CIRCUIT_BREAKER_DIAGNOSTICS.md`)
  - Error analysis tools
  - Pattern detection
  - Monitoring page enhancements

- **Monitoring Page** (`/wp-admin/admin.php?page=dmm-delivery-bridge-monitoring`)
  - Circuit breaker status display
  - Error analysis when circuit breaker is open
  - Manual reset option

## Future Enhancements

Potential improvements:

1. **Frontend Pre-check**: Check circuit breaker status in JavaScript before showing bulk send button
2. **Auto-retry**: Automatically retry bulk operations after circuit breaker resets
3. **Queue System**: Queue bulk operations when circuit breaker is open, process when it resets
4. **Notifications**: Send admin notifications when circuit breaker opens

