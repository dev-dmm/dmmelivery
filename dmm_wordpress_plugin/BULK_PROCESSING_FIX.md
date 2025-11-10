# Bulk Processing Fix - Debugging Guide

## Issues Fixed

1. **Action Scheduler Not Running**: Added fallback to immediate processing if Action Scheduler fails
2. **Progress Not Updating**: Fixed error handling to always increment counter even on errors
3. **Infinite Processing**: Added better error handling and fallback mechanisms
4. **Missing Logging**: Added comprehensive logging to track bulk operations

## What to Check in Logs

### 1. WordPress Debug Log
Location: `/home/eshop.oreksi.gr/public_html/wp-content/debug.log`

Look for:
- `Bulk send: Action Scheduler failed to schedule` - Action Scheduler is not working
- `Bulk send: Action Scheduler not available` - Action Scheduler plugin not installed
- `Bulk operation error for order X` - Individual order processing errors
- `Bulk operation completed: Job X processed Y orders` - Successful completion
- PHP errors or warnings related to bulk processing

### 2. Storage Logs
Location: `/home/oreksi.gr/dmmelivery/storage/logs/`

Check for:
- Recent log files with bulk operation entries
- Error messages about order processing
- API connection issues

## Common Issues and Solutions

### Issue: Action Scheduler Not Processing

**Symptoms:**
- Progress stays at 0%
- No errors in logs
- Actions scheduled but never executed

**Solution:**
1. Check if WordPress cron is enabled:
   ```bash
   # In WordPress, check wp-config.php
   # Should NOT have: define('DISABLE_WP_CRON', true);
   ```

2. Check Action Scheduler status:
   - Go to WooCommerce > Status > Scheduled Actions
   - Check if actions are pending

3. Manually trigger cron:
   ```bash
   # SSH into server
   wget -q -O - https://eshop.oreksi.gr/wp-cron.php?doing_wp_cron > /dev/null
   ```

### Issue: Processing Stuck at Specific Number

**Symptoms:**
- Progress stops at a certain number (e.g., 5/97)
- No errors logged

**Possible Causes:**
- Order processing is taking too long
- API timeout
- Database connection issues

**Solution:**
- Check the specific order ID that's stuck
- Check API response times
- Increase PHP timeout if needed

### Issue: All Orders Fail

**Symptoms:**
- Progress increases but all orders fail
- Many error messages in logs

**Solution:**
- Check API credentials
- Check API endpoint URL
- Verify network connectivity
- Check rate limiting

## Code Changes Made

### 1. Enhanced Error Handling
- Added try-catch around each order processing
- Always increment counter even on errors
- Log all errors for debugging

### 2. Action Scheduler Fallback
- Check if Action Scheduler is available
- Verify scheduling succeeded
- Fall back to immediate processing if needed
- Trigger queue processing on shutdown

### 3. Better Progress Tracking
- Update progress after each order
- Log completion status
- Handle missing orders gracefully

### 4. Immediate Processing Fallback
- Process orders in batches of 5
- Add delays between batches
- Update progress in real-time
- Complete job when done

## Testing the Fix

1. **Start a new bulk operation**
2. **Monitor the progress bar** - should update every few seconds
3. **Check browser console** - look for AJAX errors
4. **Check WordPress debug log** - should see processing messages
5. **Check storage logs** - should see order processing entries

## Manual Debugging Commands

### Check Current Job Status
```php
// Add to WordPress admin or run via WP-CLI
$job_id = 'bulk_send_XXXXX'; // Get from browser console
$job_data = get_transient('dmm_bulk_job_' . $job_id);
var_dump($job_data);
```

### Check Action Scheduler Status
```php
// Check if Action Scheduler is working
if (function_exists('as_get_scheduled_actions')) {
    $actions = as_get_scheduled_actions([
        'hook' => 'dmm_bulk_process_orders',
        'status' => 'pending'
    ]);
    var_dump($actions);
}
```

### Manually Process Stuck Job
```php
// Get the plugin instance
$plugin = DMM_Delivery_Bridge::getInstance();
$job_id = 'bulk_send_XXXXX';
$type = 'send';

// Manually trigger processing
$plugin->process_bulk_orders($job_id, $type);
```

## Next Steps

1. Deploy the updated code to production
2. Check the logs for any errors
3. Start a new bulk operation
4. Monitor progress and logs
5. Report any issues with specific error messages

