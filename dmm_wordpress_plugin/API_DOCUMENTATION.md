# DMM Delivery Bridge Plugin - API Documentation

## Overview

This document provides comprehensive API documentation for the DMM Delivery Bridge WordPress plugin. It covers all public methods, classes, and their usage patterns.

**Version:** 1.0.0  
**Last Updated:** 2024

---

## Table of Contents

1. [Main Plugin Class](#main-plugin-class)
2. [API Client](#api-client)
3. [Order Processor](#order-processor)
4. [Logger](#logger)
5. [Scheduler](#scheduler)
6. [Rate Limiter](#rate-limiter)
7. [Cache Service](#cache-service)
8. [Database Operations](#database-operations)
9. [Admin Interface](#admin-interface)
10. [AJAX Handlers](#ajax-handlers)

---

## Main Plugin Class

### `DMM_Delivery_Bridge`

The main plugin class implementing the Singleton pattern.

#### `getInstance()`

Get the singleton instance of the plugin.

```php
$plugin = DMM_Delivery_Bridge::getInstance();
```

**Returns:** `DMM_Delivery_Bridge` - The plugin instance

**Since:** 1.0.0

---

#### `init()`

Initialize the plugin after WordPress and WooCommerce are loaded.

**Returns:** `void`

**Since:** 1.0.0

**What it does:**
- Verifies WooCommerce is active
- Registers error handlers
- Loads text domain for internationalization
- Registers courier providers
- Sets up WooCommerce order hooks
- Registers admin menus and settings
- Schedules cleanup tasks

---

#### `handle_plugin_errors($errno, $errstr, $errfile, $errline)`

Handle plugin errors to prevent WooCommerce crashes.

**Parameters:**
- `$errno` (int) - Error number
- `$errstr` (string) - Error string
- `$errfile` (string) - Error file path
- `$errline` (int) - Error line number

**Returns:** `bool` - True to suppress error, false to let it propagate

**Since:** 1.0.0

---

#### `activate()`

Plugin activation handler. Called when plugin is activated.

**Returns:** `void`

**Since:** 1.0.0

**What it does:**
- Sets default plugin options
- Creates required database tables
- Schedules cron jobs

---

#### `deactivate()`

Plugin deactivation handler. Called when plugin is deactivated.

**Returns:** `void`

**Since:** 1.0.0

---

#### `add_order_meta_box()`

Add order meta box to WooCommerce order edit screen (HPOS-compatible).

**Returns:** `void`

**Since:** 1.0.0

---

#### `order_meta_box_content($post_or_order)`

Render order meta box content.

**Parameters:**
- `$post_or_order` (`WP_Post|WC_Order|int`) - Post object (legacy), Order object (HPOS), or Order ID

**Returns:** `void`

**Since:** 1.0.0

---

## API Client

### `DMM_API_Client`

Handles all communication with the DMM Delivery API.

#### `send_to_api($data, $courier = 'dmm', $use_cache = true)`

Send data to DMM Delivery API.

**Parameters:**
- `$data` (array) - Order data to send (must include 'order', 'customer', 'shipping' keys)
- `$courier` (string) - Courier identifier for rate limiting (default: 'dmm')
- `$use_cache` (bool) - Whether to use cache for GET requests (default: true)

**Returns:** `array` - Response array with keys:
- `success` (bool) - Whether the request succeeded
- `message` (string) - Human-readable message
- `data` (array|null) - Response data from API
- `http_code` (int) - HTTP status code
- `rate_limited` (bool, optional) - True if rate limited
- `wait_seconds` (int, optional) - Seconds to wait if rate limited

**Since:** 1.0.0

**Example:**
```php
$api_client = new DMM_API_Client($options, $logger);
$response = $api_client->send_to_api([
    'order' => [...],
    'customer' => [...],
    'shipping' => [...]
]);

if ($response['success']) {
    // Handle success
} else {
    // Handle error
}
```

---

#### `send_to_api_with_retry($order_data, $retry_count)`

Send to API with retry logic.

**Parameters:**
- `$order_data` (array) - Order data to send
- `$retry_count` (int) - Current retry attempt number (0 = first attempt)

**Returns:** `array` - Response array (same format as `send_to_api()`)

**Since:** 1.0.0

---

#### `is_retryable_error($response)`

Determine if an API error is retryable.

**Parameters:**
- `$response` (array) - Response array from `send_to_api()`

**Returns:** `bool` - True if error is retryable, false otherwise

**Since:** 1.0.0

**Retryable errors:**
- HTTP 408 (Request Timeout)
- HTTP 429 (Too Many Requests)
- HTTP 5xx (Server Errors)
- Network errors (timeouts, DNS failures, connection resets)

**Non-retryable errors:**
- HTTP 400, 401, 403, 404, 422 (client errors)

---

## Order Processor

### `DMM_Order_Processor`

Handles WooCommerce order processing and transformation.

#### `queue_send_to_api($order_id)`

Queue order for asynchronous sending to API.

**Parameters:**
- `$order_id` (int) - WooCommerce order ID

**Returns:** `void`

**Since:** 1.0.0

**Note:** This method always returns early to prevent blocking WooCommerce. All errors are caught and logged.

---

#### `maybe_queue_send_on_status($order_id, $old_status, $new_status, $order)`

Handle WooCommerce order status change events.

**Parameters:**
- `$order_id` (int) - WooCommerce order ID
- `$old_status` (string) - Previous order status
- `$new_status` (string) - New order status
- `$order` (`WC_Order`) - Order object

**Returns:** `void`

**Since:** 1.0.0

---

#### `process_order_async($args)`

Process order asynchronously via Action Scheduler.

**Parameters:**
- `$args` (array) - Action arguments containing 'order_id' key

**Returns:** `void`

**Since:** 1.0.0

**Note:** Designed to be called by Action Scheduler, not directly.

---

#### `process_order_robust($order)`

Robust order processing with retry logic and idempotency.

**Parameters:**
- `$order` (`WC_Order`) - WooCommerce order object

**Returns:** `void`

**Since:** 1.0.0

**Features:**
- Idempotency: Uses deterministic idempotency keys
- Retry logic: Automatic retries with exponential backoff
- Error handling: Comprehensive logging
- State management: Updates order meta

---

#### `prepare_order_data($order)`

Prepare WooCommerce order data for DMM Delivery API.

**Parameters:**
- `$order` (`WC_Order`) - WooCommerce order object

**Returns:** `array` - Order data array ready for API submission

**Since:** 1.0.0

**Data structure:**
```php
[
    'source' => 'woocommerce',
    'order' => [
        'external_order_id' => string,
        'order_number' => string,
        'status' => string,
        'total_amount' => float,
        'items' => array,
        // ... more fields
    ],
    'customer' => [
        'first_name' => string,
        'last_name' => string,
        'email' => string,
        'phone' => string,
    ],
    'shipping' => [
        'address' => array,
        'weight' => float,
    ],
    'create_shipment' => bool,
    'preferred_courier' => string,
]
```

---

## Logger

### `DMM_Logger`

Handles all logging operations with GDPR compliance.

#### `debug_log($message)`

Debug logging helper - only logs if debug mode is enabled.

**Parameters:**
- `$message` (string) - Log message

**Returns:** `void`

**Since:** 1.0.0

---

#### `debug_data($label, $data)`

Debug logging for structured data (arrays/objects).

**Parameters:**
- `$label` (string) - Label/description for the debug data
- `$data` (mixed) - Data to log (array, object, or any serializable value)

**Returns:** `void`

**Since:** 1.0.0

---

#### `is_debug_mode()`

Check if debug mode is enabled.

**Returns:** `bool`

**Since:** 1.0.0

---

#### `log_structured($event, $context = [])`

Structured logging helper (GDPR compliant).

**Parameters:**
- `$event` (string) - Event name (e.g., 'order_sent_success', 'api_error')
- `$context` (array) - Context data (will be sanitized for PII)

**Returns:** `void`

**Since:** 1.0.0

**PII Sanitization:**
- Email addresses are hashed (SHA-256)
- Phone numbers are hashed (SHA-256)
- Large payloads are truncated to 200 characters

---

#### `log_request($order_id, $request_data, $response)`

Log request to database.

**Parameters:**
- `$order_id` (int) - Order ID
- `$request_data` (array) - Request data
- `$response` (array) - Response data

**Returns:** `void`

**Since:** 1.0.0

---

## Scheduler

### `DMM_Scheduler`

Handles job scheduling with Action Scheduler.

#### `queue_immediate($hook, $args = [], $group = self::GROUP_IMMEDIATE, $priority = self::PRIORITY_NORMAL)`

Queue an immediate job (async execution).

**Parameters:**
- `$hook` (string) - Action hook name
- `$args` (array) - Action arguments
- `$group` (string) - Action group (default: immediate)
- `$priority` (int) - Job priority (lower = higher priority)

**Returns:** `int|false` - Action ID on success, false on failure

**Since:** 1.0.0

---

#### `schedule($timestamp, $hook, $args = [], $group = self::GROUP_SCHEDULED, $priority = self::PRIORITY_NORMAL)`

Schedule a job for later execution.

**Parameters:**
- `$timestamp` (int) - When to execute the job (Unix timestamp)
- `$hook` (string) - Action hook name
- `$args` (array) - Action arguments
- `$group` (string) - Action group (default: scheduled)
- `$priority` (int) - Job priority

**Returns:** `int|false` - Action ID on success, false on failure

**Since:** 1.0.0

---

#### `schedule_retry($hook, $args = [], $retry_count = 1)`

Schedule a retry job with exponential backoff.

**Parameters:**
- `$hook` (string) - Action hook name
- `$args` (array) - Action arguments
- `$retry_count` (int) - Current retry attempt number (1 = first retry)

**Returns:** `int|false` - Action ID on success, false if max retries reached

**Since:** 1.0.0

**Retry delays:**
- Retry 1: 1 minute (60 seconds)
- Retry 2: 2 minutes (120 seconds)
- Retry 3: 4 minutes (240 seconds)
- Retry 4: 8 minutes (480 seconds)
- Retry 5: 16 minutes (960 seconds, maximum)

**Formula:** `min(60 * 2^(retry_count - 1), 960)` seconds

---

#### `get_job_status($hook = '', $group = '', $status = '')`

Get job status information.

**Parameters:**
- `$hook` (string, optional) - Action hook name
- `$group` (string, optional) - Action group
- `$status` (string, optional) - Job status

**Returns:** `array` - Job status statistics

**Since:** 1.0.0

---

#### `monitor_job_health($log_stats = true)`

Monitor job health and log statistics.

**Parameters:**
- `$log_stats` (bool) - Whether to log statistics

**Returns:** `array` - Health status

**Since:** 1.0.0

---

## Rate Limiter

### `DMM_Rate_Limiter`

Implements token bucket algorithm for API rate limiting.

#### `check_rate_limit($courier = 'dmm', $tokens_required = 1)`

Check if request is allowed using token bucket algorithm.

**Parameters:**
- `$courier` (string) - Courier identifier (dmm, acs, geniki, elta, speedex, generic)
- `$tokens_required` (int) - Number of tokens required for this request (default: 1)

**Returns:** `array` - Response array with keys:
- `allowed` (bool) - Whether request is allowed
- `wait_seconds` (int) - Seconds to wait if not allowed (0 if allowed)
- `tokens_available` (int) - Current tokens in bucket

**Since:** 1.0.0

**Algorithm:**
- Each courier has a bucket with maximum capacity (rate limit)
- Tokens refill at constant rate (1 token per second)
- Each API request consumes tokens
- If enough tokens available, request is allowed
- If not enough tokens, request is denied with wait time

---

#### `wait_for_rate_limit($courier = 'dmm', $tokens_required = 1)`

Wait for rate limit (blocking).

**Parameters:**
- `$courier` (string) - Courier identifier
- `$tokens_required` (int) - Number of tokens required

**Returns:** `bool` - True if allowed after waiting, false if still not allowed

**Since:** 1.0.0

**Note:** Only waits if wait time is reasonable (max 60 seconds).

---

#### `handle_retry_after($courier = 'dmm', $retry_after_seconds = 0)`

Handle Retry-After header from API response.

**Parameters:**
- `$courier` (string) - Courier identifier
- `$retry_after_seconds` (int) - Seconds to wait (from Retry-After header)

**Returns:** `void`

**Since:** 1.0.0

---

## Cache Service

### `DMM_Cache_Service`

Provides unified caching interface using WordPress transients and object cache.

#### `get($key, $type = 'api_response')`

Get cached value.

**Parameters:**
- `$key` (string) - Cache key
- `$type` (string) - Cache type (for expiration lookup)

**Returns:** `mixed|false` - Cached value or false if not found

**Since:** 1.0.0

---

#### `set($key, $value, $type = 'api_response', $expiration = null)`

Set cached value.

**Parameters:**
- `$key` (string) - Cache key
- `$value` (mixed) - Value to cache
- `$type` (string) - Cache type (for expiration lookup)
- `$expiration` (int, optional) - Custom expiration in seconds

**Returns:** `bool` - Success

**Since:** 1.0.0

---

#### `delete($key)`

Delete cached value.

**Parameters:**
- `$key` (string) - Cache key

**Returns:** `bool` - Success

**Since:** 1.0.0

---

#### `cache_api_response($cache_key, $response, $expiration = null)`

Cache API response.

**Parameters:**
- `$cache_key` (string) - Unique cache key
- `$response` (array) - API response
- `$expiration` (int, optional) - Custom expiration

**Returns:** `bool` - Success

**Since:** 1.0.0

**Note:** Only caches successful responses.

---

## Database Operations

### `DMM_Database`

Handles database operations including deduplication.

#### `create_tables()`

Create all required tables (called on activation).

**Returns:** `void`

**Since:** 1.0.0

---

#### `create_dedupe_table()`

Create deduplication table.

**Returns:** `void`

**Since:** 1.0.0

---

#### `has_processed_voucher($order_id, $courier, $voucher)`

Check if voucher has been processed (database deduplication).

**Parameters:**
- `$order_id` (int) - Order ID
- `$courier` (string) - Courier name
- `$voucher` (string) - Voucher number

**Returns:** `bool|int` - False if not processed, order_id if already processed

**Since:** 1.0.0

---

#### `mark_voucher_processed($order_id, $courier, $voucher)`

Mark voucher as processed.

**Parameters:**
- `$order_id` (int) - Order ID
- `$courier` (string) - Courier name
- `$voucher` (string) - Voucher number

**Returns:** `bool` - Success

**Since:** 1.0.0

---

## Admin Interface

### `DMM_Admin`

Handles all admin interface functionality.

#### `add_admin_menu()`

Add admin menu items.

**Returns:** `void`

**Since:** 1.0.0

---

#### `admin_init()`

Initialize admin settings.

**Returns:** `void`

**Since:** 1.0.0

---

#### `get_default_options()`

Get default plugin options.

**Returns:** `array` - Default options

**Since:** 1.0.0

**Note:** Static method, can be called from anywhere.

---

#### `sanitize_options($input)`

Sanitize plugin options.

**Parameters:**
- `$input` (array) - Input options

**Returns:** `array` - Sanitized options

**Since:** 1.0.0

---

## AJAX Handlers

### `DMM_AJAX_Handlers`

Handles all AJAX requests from the admin interface.

All AJAX methods follow this pattern:
1. Verify nonce and capabilities
2. Process request
3. Return JSON response

**Common AJAX Methods:**
- `ajax_test_connection()` - Test API connection
- `ajax_resend_order()` - Resend order to API
- `ajax_sync_order()` - Sync order with API
- `ajax_bulk_send_orders()` - Bulk send orders
- `ajax_get_monitoring_data()` - Get monitoring statistics

**Since:** 1.0.0

---

## Constants

### Plugin Constants

- `DMM_DELIVERY_BRIDGE_VERSION` - Plugin version
- `DMM_DELIVERY_BRIDGE_PLUGIN_DIR` - Plugin directory path
- `DMM_DELIVERY_BRIDGE_PLUGIN_URL` - Plugin URL
- `DMM_DELIVERY_BRIDGE_PLUGIN_FILE` - Main plugin file path

---

## Hooks and Filters

### Action Hooks

- `dmm_send_order` - Triggered when order should be sent to API
- `dmm_update_order` - Triggered when order should be updated
- `dmm_register_courier_providers` - Allow 3rd parties to register courier providers
- `dmm_delivery_bridge_hooks_registered` - Fired after all hooks are registered

### Filter Hooks

- `cron_schedules` - Add custom cron intervals

---

## Error Handling

All methods are designed to:
- Never throw exceptions that could crash WooCommerce
- Log all errors using the logger
- Return early on errors
- Provide meaningful error messages

---

## Best Practices

1. **Always check return values** - Methods return arrays with 'success' keys
2. **Use idempotency keys** - Prevents duplicate sends
3. **Handle rate limiting** - Check `rate_limited` in responses
4. **Enable debug mode** - For development and troubleshooting
5. **Monitor job health** - Use scheduler monitoring methods
6. **Respect retry logic** - Don't manually retry retryable errors

---

## Examples

### Sending an Order

```php
$plugin = DMM_Delivery_Bridge::getInstance();
$order = wc_get_order(123);

// Prepare order data
$order_data = $plugin->order_processor->prepare_order_data($order);

// Send to API
$response = $plugin->api_client->send_to_api($order_data);

if ($response['success']) {
    echo "Order sent successfully!";
} else {
    echo "Error: " . $response['message'];
}
```

### Checking Rate Limits

```php
$rate_limiter = new DMM_Rate_Limiter($options, $logger);
$check = $rate_limiter->check_rate_limit('dmm', 1);

if ($check['allowed']) {
    // Make API call
} else {
    // Wait or queue for later
    echo "Rate limited. Wait {$check['wait_seconds']} seconds.";
}
```

### Logging Events

```php
$logger = new DMM_Logger($options);
$logger->log_structured('order_processing_start', [
    'order_id' => 123,
    'retry_count' => 0
]);
```

---

## Support

For issues, questions, or contributions, please refer to the main plugin documentation or contact support.

---

*Generated automatically from PHPDoc comments*

