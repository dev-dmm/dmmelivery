# Order Flow: WooCommerce → Laravel App

This document explains how orders flow from WooCommerce stores to the DMM Delivery Laravel application.

## Overview

The order flow consists of 4 main stages:
1. **WooCommerce Order Creation** (WordPress/WooCommerce)
2. **WordPress Plugin Processing** (DMM Delivery Bridge Plugin)
3. **Laravel API Reception** (WooCommerceOrderController)
4. **Frontend Display** (React/Inertia.js)

---

## Stage 1: WooCommerce Order Creation

When a customer places an order in WooCommerce:
- Order is created in WooCommerce database
- Order status is set (e.g., `pending`, `processing`, `completed`)
- Order contains: customer info, shipping address, items, totals, payment info

**Key Files:**
- WooCommerce core (not in this repo)

---

## Stage 2: WordPress Plugin Processing

### 2.1 Order Detection & Queuing

**Location:** `dmm_wordpress_plugin/includes/class-dmm-delivery-bridge.php`

The plugin hooks into WooCommerce order events:
```php
// Triggers when order status changes
add_action('woocommerce_order_status_changed', [$this->order_processor, 'maybe_queue_send_on_status'], 20, 4);
add_action('woocommerce_payment_complete', [$this->order_processor, 'queue_send_to_api'], 20, 1);
add_action('woocommerce_order_status_processing', [$this->order_processor, 'queue_send_to_api'], 20);
add_action('woocommerce_order_status_completed', [$this->order_processor, 'queue_send_to_api'], 20);
```

**Location:** `dmm_wordpress_plugin/includes/class-dmm-order-processor.php`

The `queue_send_to_api()` method:
- Checks if auto-send is enabled
- Verifies order status is in allowed list (configurable)
- Checks if order was already sent successfully
- Sets a transient lock to prevent race conditions
- Queues the order for async processing using Action Scheduler

### 2.2 Order Data Preparation

**Location:** `dmm_wordpress_plugin/includes/class-dmm-order-processor.php::prepare_order_data()`

Transforms WooCommerce order into API format:
```php
[
    'source' => 'woocommerce',
    'order' => [
        'external_order_id' => '12345',  // WooCommerce order ID
        'order_number' => 'WC-12345',
        'status' => 'processing',
        'total_amount' => 99.99,
        'subtotal' => 89.99,
        'tax_amount' => 10.00,
        'shipping_cost' => 5.00,
        'discount_amount' => 0,
        'currency' => 'EUR',
        'payment_status' => 'paid',
        'payment_method' => 'stripe',
        'items' => [...]
    ],
    'customer' => [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '+30...'
    ],
    'shipping' => [
        'address' => [...],
        'weight' => 1.5
    ],
    'create_shipment' => true,
    'preferred_courier' => 'acs'
]
```

**Key Features:**
- Falls back to billing address if shipping address is missing
- Extracts product images (main image, gallery, or placeholder)
- Calculates total weight from product weights
- Strips HTML tags for security
- Handles errors gracefully (returns minimal valid data)

### 2.3 API Request

**Location:** `dmm_wordpress_plugin/includes/class-dmm-api-client.php::send_to_api()`

Sends HTTP POST request to Laravel API:
- **Endpoint:** `{api_endpoint}/api/woocommerce/order`
- **Method:** POST
- **Headers:**
  - `Content-Type: application/json`
  - `X-Api-Key: {api_key}`
  - `X-Tenant-Id: {tenant_id}`
  - `X-Idempotency-Key: {hmac_sha256_hash}`
  - `X-Payload-Signature: {hmac_signature}` (for security)

**Features:**
- Rate limiting (prevents API abuse)
- Circuit breaker (stops requests if API is down)
- Retry logic with exponential backoff
- Idempotency key (prevents duplicate orders)
- HMAC signature verification

---

## Stage 3: Laravel API Reception

### 3.1 Route & Middleware

**Location:** `routes/api.php`

```php
Route::prefix('woocommerce')
    ->middleware(['throttle:woocommerce', 'woo.ratelimit.headers', 'woo.hmac'])
    ->group(function () {
        Route::post('/order', [WooCommerceOrderController::class, 'store']);
    });
```

**Middleware Chain:**
1. **Rate Limiting:** Limits requests per minute per tenant/IP
2. **Rate Limit Headers:** Adds rate limit info to response
3. **HMAC Verification:** Validates request signature for security

### 3.2 Controller Processing

**Location:** `app/Http/Controllers/WooCommerceOrderController.php::store()`

#### Step 1: Request Validation
- Validates required fields (external_order_id, total_amount, shipping address, etc.)
- Extracts tenant ID from headers or request body
- Validates tenant exists and is active

#### Step 2: Race Condition Prevention
Uses Redis lock to prevent duplicate order creation:
```php
$lockKey = "orders:create:{$tenantId}:{$externalId}";
return \Cache::lock($lockKey, 10)->block(5, function () {
    // Process order creation
});
```

#### Step 3: Customer Creation/Resolution
**Location:** `app/Http/Controllers/WooCommerceOrderController.php::doStore()`

- Checks if customer exists (by email + tenant)
- Creates `Customer` record if doesn't exist
- Links to `GlobalCustomer` if email matches across tenants
- Stores customer info: name, email, phone, address

#### Step 4: Order Creation
**Location:** `app/Http/Controllers/WooCommerceOrderController.php::doStore()`

Creates `Order` record with:
- Tenant ID
- Customer ID
- External order ID (WooCommerce order ID)
- Order number
- Status, totals, currency
- Shipping/billing addresses
- Payment information
- Order items (if provided)

**Database Transaction:** All operations wrapped in DB transaction for data integrity.

#### Step 5: Order Items Creation
**Location:** `app/Http/Controllers/WooCommerceOrderController.php::createOrderItems()`

If order items are provided:
- Creates `OrderItem` records for each product
- Stores: SKU, name, quantity, prices, weight, product images
- Links to order

If no items provided:
- Creates a generic order item with order total

#### Step 6: Shipment Creation (Optional)
**Location:** `app/Http/Controllers/WooCommerceOrderController.php::doStore()`

**Shipment Creation Logic:**
- **If order has voucher:** Creates shipment using courier from voucher
  - Matches courier by name or code (case-insensitive) from `courier_company`
  - Uses voucher number as tracking number
  - If voucher courier not found in database → Skips shipment creation (configuration error)
- **If order has NO voucher:** Does NOT create shipment
  - Order is created successfully
  - Shipment waits for voucher to be added later
  - When voucher is added (via order update), shipment will be created automatically
**Voucher Information Storage:**
- Voucher number and courier company stored in `order.additional_data` JSON field
- Logged for tracking and debugging

#### Step 7: Response
Returns JSON response:
```json
{
    "success": true,
    "message": "Order created successfully",
    "order_id": "uuid",
    "shipment_id": "uuid",
    "tracking_number": "ABC123XYZ"
}
```

**Error Handling:**
- Returns 422 for validation errors
- Returns 423 if lock timeout (busy)
- Returns 500 for server errors
- Logs all errors for debugging

---

## Stage 4: Frontend Display

### 4.1 API Endpoints

**Location:** `routes/api/v1.php`

Orders are accessible via:
- `GET /api/v1/orders` - List orders (with filters)
- `GET /api/v1/orders/{id}` - Get single order
- `POST /api/v1/orders/{order}/create-shipment` - Create shipment for order

**Authentication:** Requires Sanctum token + tenant enforcement

### 4.2 React Components

**Location:** `resources/js/features/`

Orders are displayed in:
- **Super Admin Dashboard:** `resources/js/features/super-admin/pages/Dashboard.jsx`
  - Shows recent orders across all tenants
- **Super Admin Orders Page:** `resources/js/features/super-admin/pages/Orders.jsx`
  - Full orders list with filters (tenant, status, search)
  - Shows: order number, tenant, customer, status, total, shipment info

**Data Flow:**
1. React component makes API request via Inertia.js
2. Laravel controller fetches orders (with relationships: tenant, customer, shipments)
3. Data returned as JSON
4. React component renders order list/table

---

## Data Models

### Order Model
**Location:** `app/Models/Order.php`

**Key Fields:**
- `tenant_id` - Multi-tenant isolation
- `customer_id` - Links to Customer
- `external_order_id` - WooCommerce order ID
- `order_number` - Display number
- `status` - Order status (pending, processing, shipped, delivered, etc.)
- `total_amount`, `subtotal`, `tax_amount`, `shipping_cost`
- Shipping/billing addresses
- Payment information

**Relationships:**
- `tenant()` - BelongsTo Tenant
- `customer()` - BelongsTo Customer
- `items()` - HasMany OrderItem
- `shipments()` - HasMany Shipment
- `primaryShipment()` - HasOne Shipment (via shipment_id)

### Customer Model
**Location:** `app/Models/Customer.php`

Stores customer information per tenant, linked to GlobalCustomer for cross-tenant matching.

### Shipment Model
**Location:** `app/Models/Shipment.php`

Tracks delivery status, courier, tracking number, etc.

---

## Key Features

### 1. Idempotency
- WordPress plugin generates idempotency key (HMAC-SHA256)
- Laravel checks for duplicate orders using external_order_id + tenant_id
- Prevents duplicate orders if request is retried

### 2. Race Condition Prevention
- Redis locks prevent concurrent order creation
- Transient locks in WordPress prevent duplicate queuing

### 3. Error Handling & Retries
- WordPress plugin retries failed requests (up to 5 times)
- Exponential backoff between retries
- Circuit breaker stops requests if API is consistently failing

### 4. Multi-Tenancy
- All orders are scoped to tenant
- Tenant ID required in API request
- Tenant middleware enforces tenant isolation

### 5. Logging
- Comprehensive logging at each stage
- WordPress plugin logs: order processing, API requests/responses
- Laravel logs: order creation, errors, warnings

---

## Configuration

### WordPress Plugin Settings
**Location:** WordPress Admin → DMM Delivery Bridge

- **API Endpoint:** Laravel API URL
- **API Key:** Authentication key
- **Tenant ID:** Tenant identifier
- **Auto Send:** Enable/disable automatic order sending
- **Order Statuses:** Which statuses trigger sending
- **Create Shipment:** Whether to auto-create shipments

### Laravel Configuration
**Location:** `config/`

- **Rate Limiting:** `config/rate.php` - WooCommerce API rate limits
- **Tenancy:** `config/tenancy.php` - Multi-tenant settings

---

## Troubleshooting

### Order Not Appearing in App

1. **Check WordPress Plugin:**
   - Verify API endpoint, key, and tenant ID are correct
   - Check if order status is in allowed list
   - Check plugin logs: `wp-content/debug.log`

2. **Check Laravel Logs:**
   - `storage/logs/laravel.log`
   - Look for validation errors, tenant issues, database errors

3. **Check API Response:**
   - WordPress plugin logs API responses
   - Verify response has `success: true`

### Duplicate Orders

- Check idempotency key generation
- Verify Redis lock is working
- Check for multiple WordPress sites sending same order

### Missing Shipments

- Verify courier is configured for tenant
- Check `create_shipment` setting in plugin
- Check Laravel logs for courier lookup warnings

---

## Summary Flow Diagram

```
WooCommerce Order Created
    ↓
WordPress Plugin Detects Order (status change hook)
    ↓
Plugin Queues Order (Action Scheduler)
    ↓
Plugin Prepares Order Data (transform WC order → API format)
    ↓
Plugin Sends HTTP POST to Laravel API
    ↓
Laravel Middleware: Rate Limit + HMAC Verification
    ↓
WooCommerceOrderController::store()
    ↓
Redis Lock (prevent race conditions)
    ↓
Create/Find Customer
    ↓
Create Order (DB transaction)
    ↓
Create Order Items
    ↓
Create Shipment (if enabled + courier exists)
    ↓
Return Success Response
    ↓
WordPress Plugin Marks Order as Sent
    ↓
Order Appears in Laravel App Frontend
```

---

## Bulk Send: Processing Older Orders

When you first install the WordPress plugin or want to send existing orders that weren't automatically sent, you can use the **Bulk Send** feature.

### How to Access Bulk Send

**Location:** WordPress Admin → DMM Delivery Bridge → Bulk Processing

### What Bulk Send Does

#### Step 1: Finding Pending Orders

**Location:** `dmm_wordpress_plugin/includes/class-dmm-ajax-handlers.php::ajax_bulk_send_orders()`

The plugin searches for orders that:
- Have status `processing` or `completed`
- Do NOT have the `_dmm_sent_to_api` meta field (meaning they haven't been sent yet)
- Are NOT refunds (excludes `WC_Order_Refund` objects)

**Query:**
```php
$args = [
    'status' => ['processing', 'completed'],
    'limit' => -1, // All matching orders
    'type' => 'shop_order', // Exclude refunds
    'meta_query' => [
        [
            'key' => '_dmm_sent_to_api',
            'compare' => 'NOT EXISTS' // Only unsent orders
        ]
    ]
];
```

#### Step 2: Creating a Bulk Job

The plugin creates a job to track progress:
- **Job ID:** `bulk_send_{timestamp}_{random_string}`
- **Job Data Stored in Transient:**
  - `type`: 'send'
  - `total`: Total number of orders found
  - `current`: Current progress (starts at 0)
  - `status`: 'running' (or 'completed', 'cancelled')
  - `order_ids`: Array of all order IDs to process
  - `started_at`: Timestamp when job started

**Location:** `dmm_wordpress_plugin/includes/class-dmm-ajax-handlers.php::ajax_bulk_send_orders()`

#### Step 3: Processing in Batches

**Batch Size:** 5 orders per batch (configurable)

**Processing Strategy:**
1. **First Batch (Immediate):** Processes first 5 orders immediately for instant feedback
2. **Remaining Batches:** Scheduled using Action Scheduler (if available) or processed via AJAX polling

**Location:** `dmm_wordpress_plugin/includes/class-dmm-delivery-bridge.php::process_bulk_orders()`

**Flow:**
```
Batch 1 (5 orders) → Process immediately
    ↓
Batch 2 (5 orders) → Schedule 1 second later
    ↓
Batch 3 (5 orders) → Schedule 1 second later
    ↓
... continues until all orders processed
```

#### Step 4: Processing Each Order

For each order in a batch:

**Location:** `dmm_wordpress_plugin/includes/class-dmm-order-processor.php::process_order_robust()`

1. **Check if Already Sent:**
   - If `_dmm_delivery_sent === 'yes'`, skip order
   - Prevents duplicate sends

2. **Generate Idempotency Key:**
   - Creates HMAC-SHA256 hash for duplicate prevention
   - Same key used for retries

3. **Check Retry Count:**
   - If retry count >= 5, skip order (max retries reached)
   - Otherwise, proceed

4. **Prepare Order Data:**
   - Transforms WooCommerce order to API format
   - Same as regular order flow (see Stage 2.2 above)
   - **Extracts voucher number and courier company** using comprehensive checking (see "Voucher & Courier Detection" below)
   - Includes `voucher_number`, `courier_company`, and `preferred_courier` in API payload

5. **Send to API:**
   - Uses `api_client->send_to_api_with_retry()`
   - Includes retry logic with exponential backoff
   - Handles rate limiting and circuit breaker

6. **Handle Response:**
   - **Success:** Marks order as sent (`_dmm_delivery_sent = 'yes'`)
   - **Failure:** Increments retry count, logs error, continues to next order

#### Step 5: Progress Tracking

**Real-time Updates:**
- Job progress stored in WordPress transient
- Frontend polls progress via AJAX
- Updates progress bar: `current / total * 100%`

**Location:** `dmm_wordpress_plugin/admin/js/admin.js`

**Progress Data:**
```javascript
{
    job_id: 'bulk_send_1234567890_abc123',
    total: 150,
    current: 45,
    status: 'running',
    percentage: 30
}
```

#### Step 6: Completion

When all orders are processed:
- Job status set to `'completed'`
- Final count logged
- Transient kept for 1 hour (for review)
- Success message displayed to user

### Bulk Send Features

#### 1. **Background Processing**
- Operations run asynchronously
- Doesn't block WordPress admin
- Can navigate away from page

#### 2. **Action Scheduler Integration**
- Uses Action Scheduler plugin if available
- Schedules batches 1 second apart
- Falls back to AJAX polling if Action Scheduler unavailable

#### 3. **Error Handling**
- Individual order failures don't stop the job
- Errors logged for each failed order
- Job continues processing remaining orders

#### 4. **Cancellation**
- Can cancel bulk job at any time
- Sets job status to `'cancelled'`
- Stops scheduling new batches

**Location:** `dmm_wordpress_plugin/includes/class-dmm-ajax-handlers.php::ajax_cancel_bulk_send()`

#### 5. **Resend Failed Orders**
- Separate bulk operation for failed orders
- Finds orders with `_dmm_delivery_sent = 'failed'`
- Resets retry count and attempts again

### Bulk Send vs Regular Send

| Feature | Regular Send | Bulk Send |
|---------|-------------|-----------|
| **Trigger** | Order status change hook | Manual button click |
| **Processing** | Single order, immediate | Multiple orders, batched |
| **Timing** | Real-time (when order changes) | On-demand (when you click) |
| **Use Case** | New orders | Older/existing orders |
| **Progress Tracking** | No (instant) | Yes (progress bar) |
| **Background** | Yes (Action Scheduler) | Yes (Action Scheduler/AJAX) |

### What Happens in Laravel API

**Same Processing as Regular Orders:**
- Bulk send uses the same API endpoint: `/api/woocommerce/order`
- Same validation, customer creation, order creation logic
- Same shipment creation (if enabled)
- Same idempotency checks (prevents duplicates if order already exists)

**Key Difference:**
- Bulk send processes many orders quickly
- Laravel API handles each request independently
- Rate limiting may slow down if too many requests arrive at once

### Bulk Send Flow Diagram

```
User Clicks "Bulk Send" in WordPress Admin
    ↓
Plugin Finds All Pending Orders (status: processing/completed, not sent)
    ↓
Creates Bulk Job (stores in transient)
    ↓
Processes First Batch (5 orders) Immediately
    ↓
For Each Order in Batch:
    ├─ Check if already sent → Skip if yes
    ├─ Generate idempotency key
    ├─ Prepare order data
    ├─ Send to Laravel API (POST /api/woocommerce/order)
    ├─ Handle response (success/failure)
    └─ Update progress counter
    ↓
Schedule Next Batch (1 second later)
    ↓
Repeat until all orders processed
    ↓
Mark Job as Completed
    ↓
Display Success Message
```

### Voucher & Courier Detection

**Important:** The plugin checks for vouchers and courier companies, but this works differently for **display** vs **order processing**.

#### For Display (WooCommerce Orders List)

**Location:** `dmm_wordpress_plugin/includes/class-dmm-delivery-bridge.php::get_courier_voucher_display()`

The plugin comprehensively checks for vouchers from multiple couriers:

**Couriers Checked:**
- **ELTA Hellenic Post:** Checks meta fields like `_elta_voucher`, `elta_voucher`, `elta_tracking`, `_elta_tracking`, `elta_reference`, `_elta_reference`
- **Geniki Taxidromiki:** Checks `_geniki_voucher`, `geniki_voucher`, `geniki_tracking`, `_geniki_tracking`, `gtx_voucher`, `gtx_tracking`
- **ACS Courier:** Checks `_acs_voucher`, `acs_voucher`, `acs_tracking`, `_appsbyb_acs_courier_gr_no_pod`

**Configurable Meta Fields:**
- Each courier has a configurable meta field in plugin settings
- If configured, only that field is checked
- If not configured, falls back to default field list

**Result:** Shows courier name (e.g., "ACS", "ELTA") in orders list if voucher found, or "No voucher yet" if none.

#### For Order Processing (Sending to API)

**Location:** `dmm_wordpress_plugin/includes/class-dmm-order-processor.php::get_courier_voucher_from_order()`

**Comprehensive Checking:**
The plugin now uses the same comprehensive voucher checking as the display function:

**Couriers Checked:**
- **ELTA Hellenic Post:** Checks meta fields like `_elta_voucher`, `elta_voucher`, `elta_tracking`, `_elta_tracking`, `elta_reference`, `_elta_reference`
- **Geniki Taxidromiki:** Checks `_geniki_voucher`, `geniki_voucher`, `geniki_tracking`, `_geniki_tracking`, `gtx_voucher`, `gtx_tracking`
- **ACS Courier:** Checks `_acs_voucher`, `acs_voucher`, `acs_tracking`, `_appsbyb_acs_courier_gr_no_pod`
- **Speedex:** Checks `obs_speedex_courier`, `obs_speedex_courier_pieces`

**Configurable Meta Fields:**
- Each courier has a configurable meta field in plugin settings
- If configured, only that field is checked
- If not configured, falls back to default field list

**What Gets Sent:**
- `preferred_courier`: Courier company name (e.g., "acs", "geniki", "elta", "speedex")
- `voucher_number`: The actual voucher/tracking number extracted from order meta
- `courier_company`: Same as preferred_courier (for clarity)

**Priority:**
- Checks couriers in order: ELTA → Geniki → ACS → Speedex
- Returns first match found (stops checking after first voucher found)
- Falls back to generic fields (`courier`, `shipping_courier`, `voucher_number`, `tracking_number`) if no specific courier voucher found

#### Voucher Meta Field Mapping

**Location:** `dmm_wordpress_plugin/dm-delivery-bridge.php::dmm_voucher_meta_map()`

The plugin defines a comprehensive mapping of voucher meta fields per courier:

```php
[
    'acs' => [
        '_acs_voucher',
        'acs_voucher', 
        'acs_tracking',
        '_appsbyb_acs_courier_gr_no_pod',
        '_appsbyb_acs_courier_gr_no_pod_pieces'
    ],
    'speedex' => [
        'obs_speedex_courier',
        'obs_speedex_courier_pieces'
    ],
    'generic' => [
        'voucher_number',
        'tracking_number',
        'shipment_id',
        '_dmm_delivery_shipment_id',
        'courier',
        'shipping_courier',
        // ... more fields
    ]
]
```

**Note:** This mapping is available but **not actively used during order processing**. It's primarily for reference and potential future use.

#### Summary

| Feature | Display (Orders List) | Order Processing (API Send) |
|---------|----------------------|----------------------------|
| **Courier Detection** | ✅ Comprehensive (ELTA, Geniki, ACS) | ✅ Comprehensive (ELTA, Geniki, ACS, Speedex) |
| **Voucher Extraction** | ✅ Yes (checks multiple fields) | ✅ Yes (checks multiple fields) |
| **Voucher Number Sent** | N/A (display only) | ✅ Yes (sent to API) |
| **Courier Company Sent** | N/A (display only) | ✅ Yes (sent to API) |
| **Configurable Meta Fields** | ✅ Yes (per courier) | ✅ Yes (per courier) |
| **Result** | Shows courier name in list | Sends `voucher_number`, `courier_company`, and `preferred_courier` |

**Laravel API Handling:**
- Voucher information stored in `order.additional_data` JSON field
- If voucher number provided, it's used as the shipment `tracking_number`
- If no voucher, generates a random tracking number (as before)
- Voucher and courier company logged for tracking

### Troubleshooting Bulk Send

#### No Orders Found
- **Check:** Order status must be `processing` or `completed`
- **Check:** Orders must not have `_dmm_sent_to_api` meta field
- **Solution:** Use "Resend Failed Orders" if orders were previously attempted

#### Job Stuck / Not Progressing
- **Check:** Action Scheduler plugin installed and active
- **Check:** WordPress cron is running (`wp cron event list`)
- **Check:** PHP execution time limits
- **Solution:** Check plugin logs for errors

#### Some Orders Fail
- **Check:** Laravel API logs for validation errors
- **Check:** API endpoint, key, tenant ID are correct
- **Check:** Network connectivity between WordPress and Laravel
- **Solution:** Failed orders can be resent individually or via "Resend Failed Orders"

#### Duplicate Orders Created
- **Check:** Idempotency key generation
- **Check:** Laravel duplicate detection (external_order_id + tenant_id)
- **Solution:** Laravel should prevent duplicates, but check if same order sent multiple times

### Related Files

**WordPress Plugin:**
- `dmm_wordpress_plugin/includes/class-dmm-ajax-handlers.php` - AJAX handlers for bulk operations
- `dmm_wordpress_plugin/includes/class-dmm-delivery-bridge.php` - Bulk processing logic
- `dmm_wordpress_plugin/admin/views/bulk-page.php` - Bulk processing UI
- `dmm_wordpress_plugin/admin/js/admin.js` - Frontend progress tracking

---

## Related Files

### WordPress Plugin
- `dmm_wordpress_plugin/dm-delivery-bridge.php` - Main plugin file
- `dmm_wordpress_plugin/includes/class-dmm-delivery-bridge.php` - Core class
- `dmm_wordpress_plugin/includes/class-dmm-order-processor.php` - Order processing
- `dmm_wordpress_plugin/includes/class-dmm-api-client.php` - API communication

### Laravel Backend
- `routes/api.php` - WooCommerce API routes
- `app/Http/Controllers/WooCommerceOrderController.php` - Order controller
- `app/Models/Order.php` - Order model
- `app/Models/Customer.php` - Customer model
- `app/Models/Shipment.php` - Shipment model

### Frontend
- `resources/js/features/super-admin/pages/Orders.jsx` - Orders list
- `resources/js/features/super-admin/pages/Dashboard.jsx` - Dashboard with orders
- `routes/api/v1.php` - Frontend API routes

