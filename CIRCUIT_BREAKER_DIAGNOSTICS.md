# Circuit Breaker Diagnostics Guide

## Overview

The circuit breaker is a safety mechanism that automatically disables API calls when the error rate exceeds a threshold (10 errors in 5 minutes). This prevents overwhelming a failing API and wasting resources.

## Understanding the Circuit Breaker

### How It Works

1. **Error Tracking**: The system tracks all API errors in the `dmm_delivery_logs` table
2. **Threshold**: When 10+ errors occur within 5 minutes, the circuit breaker opens
3. **Duration**: The circuit breaker stays open for 10 minutes (600 seconds) by default
4. **Auto-Reset**: The circuit breaker automatically resets after the timeout period

### Error Log Entry Analysis

Based on the provided log entry (Log ID: 421, Order ID: 500):

```json
{
  "Status": "error",
  "Error Message": "API calls are temporarily disabled due to high error rate.",
  "Context": "api"
}
```

This error indicates that:
- The circuit breaker was already open when this order was processed
- The order was blocked from being sent to the API
- This is a **symptom**, not the root cause

## New Diagnostic Features

### 1. Error Analysis Methods

Added to `DMM_API_Client` class:

#### `get_recent_errors($minutes = 5, $limit = 50)`
Retrieves recent error logs for analysis.

**Parameters:**
- `$minutes`: Number of minutes to look back (default: 5)
- `$limit`: Maximum number of errors to return (default: 50)

**Returns:** Array of error log entries with:
- `id`: Log entry ID
- `order_id`: WooCommerce order ID
- `error_message`: Error message
- `context`: Context (usually 'api')
- `created_at`: Timestamp
- `response_data`: Parsed response data (if available)

#### `analyze_error_patterns($minutes = 5)`
Analyzes recent errors to identify common patterns and root causes.

**Parameters:**
- `$minutes`: Number of minutes to analyze (default: 5)

**Returns:** Analysis array with:
- `total_errors`: Total number of errors
- `error_patterns`: Array of error message patterns and counts
- `http_codes`: Array of HTTP status codes and counts
- `common_messages`: Most common error messages
- `time_range`: Time range analyzed

### 2. AJAX Endpoints

Added to `DMM_Ajax_Handlers` class:

#### `dmm_get_recent_errors`
Retrieves recent errors via AJAX.

**POST Parameters:**
- `minutes`: Number of minutes to look back (1-60, default: 5)
- `limit`: Maximum number of errors (1-200, default: 50)

#### `dmm_analyze_error_patterns`
Analyzes error patterns via AJAX.

**POST Parameters:**
- `minutes`: Number of minutes to analyze (1-60, default: 5)

### 3. Enhanced Monitoring Page

The monitoring page (`/wp-admin/admin.php?page=dmm-delivery-bridge-monitoring`) now includes:

#### Error Analysis Card
- Automatically appears when the circuit breaker is open
- Shows:
  - **Summary**: Total errors and time range
  - **Error Patterns**: Categorized error types (rate limiting, server errors, timeouts, etc.)
  - **HTTP Status Codes**: Distribution of HTTP error codes
  - **Common Error Messages**: Most frequent error messages
  - **Recent Errors**: Table of recent error logs

## Troubleshooting Steps

### Step 1: Check Circuit Breaker Status

1. Navigate to **DMM Delivery Bridge → Monitoring**
2. Check the "Circuit Breaker Status" card
3. If open, note:
   - Reason for opening
   - Time remaining until auto-reset
   - Reset time

### Step 2: Analyze Error Patterns

When the circuit breaker is open, the "Error Analysis" card will automatically appear:

1. Review the **Error Patterns** section to identify common issues:
   - **Rate limiting (HTTP 429)**: API is being called too frequently
   - **Server errors (HTTP 500/502/503)**: API server is experiencing issues
   - **Request timeout**: Network or API response time issues
   - **Connection errors**: Network connectivity problems
   - **Authentication errors**: API key or credentials issues
   - **Validation errors**: Data format or validation issues

2. Check **HTTP Status Codes** to see the distribution of error types

3. Review **Common Error Messages** to identify specific issues

### Step 3: Investigate Root Causes

Based on the error patterns, investigate:

#### Rate Limiting (429)
- Check if multiple orders are being processed simultaneously
- Review rate limit settings in the API client
- Consider implementing request queuing

#### Server Errors (500/502/503)
- Check API server health and logs
- Verify API endpoint is accessible
- Check for API service outages

#### Timeouts
- Check network connectivity
- Verify API response times
- Review timeout settings (currently 20 seconds)

#### Connection Errors
- Verify API endpoint URL is correct
- Check firewall/network settings
- Test API endpoint accessibility

#### Authentication Errors
- Verify API key is correct and not expired
- Check tenant ID is correct
- Verify API credentials have proper permissions

### Step 4: Resolve Issues

1. **Fix the root cause** of the errors
2. **Wait for auto-reset** (10 minutes) OR
3. **Manually reset** the circuit breaker (only if you've fixed the issue):
   - Click "🔓 Reset Circuit Breaker Now" button
   - ⚠️ **Warning**: Only reset if you've resolved the underlying issue

### Step 5: Monitor After Reset

After resetting:
1. Monitor the error logs for new errors
2. Check if the same error patterns reoccur
3. If errors persist, investigate further

## Common Scenarios

### Scenario 1: API Server Down
**Symptoms:**
- HTTP 500/502/503 errors
- Connection errors
- Timeouts

**Solution:**
- Check API server status
- Wait for server to recover
- Reset circuit breaker after server is back online

### Scenario 2: Rate Limiting
**Symptoms:**
- HTTP 429 errors
- "Rate limit exceeded" messages

**Solution:**
- Reduce order processing frequency
- Implement request queuing
- Contact API provider to increase rate limits

### Scenario 3: Configuration Issues
**Symptoms:**
- Authentication errors
- "API configuration is incomplete" messages
- Validation errors

**Solution:**
- Verify API endpoint, key, and tenant ID in settings
- Check API credentials are correct
- Review order data format

### Scenario 4: Network Issues
**Symptoms:**
- Connection errors
- DNS resolution errors
- Timeouts

**Solution:**
- Check network connectivity
- Verify firewall rules
- Test API endpoint accessibility

## Best Practices

1. **Monitor Regularly**: Check the monitoring page regularly to catch issues early
2. **Don't Reset Prematurely**: Only reset the circuit breaker after fixing the root cause
3. **Review Error Patterns**: Use error analysis to identify systemic issues
4. **Document Issues**: Keep track of recurring error patterns for future reference
5. **Set Up Alerts**: Consider setting up alerts for circuit breaker openings

## API Methods Reference

### PHP Usage

```php
// Get API client instance
$api_client = $plugin->api_client;

// Get recent errors
$errors = $api_client->get_recent_errors(5, 50);

// Analyze error patterns
$analysis = $api_client->analyze_error_patterns(5);

// Get circuit breaker status
$status = $api_client->get_circuit_breaker_status();

// Reset circuit breaker (use with caution!)
$reset = $api_client->reset_circuit_breaker();
```

### JavaScript/AJAX Usage

```javascript
// Get recent errors
fetch(ajaxurl, {
    method: 'POST',
    body: new FormData().append('action', 'dmm_get_recent_errors')
        .append('nonce', nonce)
        .append('minutes', '5')
        .append('limit', '50')
})
.then(r => r.json())
.then(data => console.log(data));

// Analyze error patterns
fetch(ajaxurl, {
    method: 'POST',
    body: new FormData().append('action', 'dmm_analyze_error_patterns')
        .append('nonce', nonce)
        .append('minutes', '5')
})
.then(r => r.json())
.then(data => console.log(data));
```

## Technical Details

### Circuit Breaker Thresholds

- **Error Threshold**: 10 errors in 5 minutes
- **Open Duration**: 10 minutes (600 seconds) for high error rate
- **Open Duration**: 5 minutes (300 seconds) for specific error patterns (rate limiting, server errors)

### Error Tracking

- Errors are stored in `wp_dmm_delivery_logs` table
- Uses composite index `(status, created_at)` for optimal query performance
- Error count is cached for 30 seconds to reduce database load

### Performance Considerations

- Error analysis queries are limited to 200 errors maximum
- Time range is limited to 60 minutes maximum
- Results are cached where appropriate to reduce database load

