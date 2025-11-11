# Bulk Send Orders - Courier and Voucher Detection

## Overview

This document explains how the WordPress plugin handles **bulk sending orders** and how it determines the **courier** and **voucher number** from WooCommerce orders.

---

## What is "Bulk Send Orders"?

**Bulk Send Orders** is a feature that allows administrators to send multiple WooCommerce orders to the DMM Delivery API at once, rather than processing them individually.

### How It Works

1. **Trigger**: Admin initiates bulk send from the WordPress admin interface (bulk operations page)
2. **Order Selection**: The system finds all orders that:
   - Have status `processing` or `completed`
   - Have NOT been sent to the API yet (no `_dmm_sent_to_api` meta field)
   - Are NOT refunds (only `shop_order` type)
3. **Processing**: Orders are processed in batches:
   - First 5 orders are processed immediately
   - Remaining orders are scheduled via Action Scheduler (or processed via AJAX polling if Action Scheduler is unavailable)
4. **Progress Tracking**: Job progress is tracked via WordPress transients and displayed in the admin UI

### Code Location

- **AJAX Handler**: `dmm_wordpress_plugin/includes/class-dmm-ajax-handlers.php` → `ajax_bulk_send_orders()`
- **Batch Processing**: `dmm_wordpress_plugin/includes/class-dmm-ajax-handlers.php` → `process_bulk_orders_batch()`
- **Order Processing**: `dmm_wordpress_plugin/includes/class-dmm-order-processor.php` → `process_order_robust()`

---

## How Courier is Determined

The plugin determines the courier by checking **WooCommerce order meta fields** in a specific priority order.

### Detection Method

The courier detection happens in the `get_courier_voucher_from_order()` method (lines 712-775 in `class-dmm-order-processor.php`).

### Priority Order (First Match Wins)

1. **ELTA Courier**
   - Checks configured meta field from settings (`elta_voucher_meta_field`)
   - Falls back to: `_elta_voucher`, `elta_voucher`, `elta_tracking`, `_elta_tracking`, `elta_reference`, `_elta_reference`

2. **Geniki Courier**
   - Checks configured meta field from settings (`geniki_voucher_meta_field`)
   - Falls back to: `_geniki_voucher`, `geniki_voucher`, `geniki_tracking`, `_geniki_tracking`, `gtx_voucher`, `gtx_tracking`

3. **ACS Courier**
   - Checks configured meta field from settings (`acs_voucher_meta_field`)
   - Falls back to: `_acs_voucher`, `acs_voucher`, `acs_tracking`, `_appsbyb_acs_courier_gr_no_pod`

4. **Speedex Courier**
   - Checks: `obs_speedex_courier`, `obs_speedex_courier_pieces`

5. **Generic Fallback Fields**
   - If no specific courier voucher found, checks generic fields:
     - `courier` - Returns courier name directly
     - `shipping_courier` - Returns courier name directly
     - `voucher_number` - Tries to infer courier from `courier` or `shipping_courier` meta
     - `tracking_number` - Tries to infer courier from `courier` or `shipping_courier` meta

### Return Value

- Returns **lowercase courier name** (e.g., `"elta"`, `"geniki"`, `"acs"`, `"speedex"`)
- Empty string if no courier found

### Code Example

```php
// In prepare_order_data() method (line 632)
$courier_voucher_info = $this->get_courier_voucher_from_order($order);
if (!empty($courier_voucher_info['courier'])) {
    $order_data['preferred_courier'] = $courier_voucher_info['courier'];
}
```

---

## How Voucher Number is Determined

The voucher number is extracted from the **same order meta fields** used for courier detection.

### Detection Method

The voucher detection happens in the **same method** as courier detection: `get_courier_voucher_from_order()`.

### How It Works

1. **Primary Detection**: When a courier-specific meta field is found (e.g., `_elta_voucher`), the **value** of that field becomes the voucher number
2. **Courier-Voucher Pairing**: The voucher number is always paired with its corresponding courier
3. **Fallback**: If only generic fields like `voucher_number` or `tracking_number` are found, their values are used as the voucher number

### Return Value

- Returns the **trimmed voucher/tracking number** as a string
- Empty string if no voucher found

### Code Example

```php
// In prepare_order_data() method (lines 636-639)
if (!empty($courier_voucher_info['voucher_number'])) {
    $order_data['voucher_number'] = $courier_voucher_info['voucher_number'];
    $order_data['courier_company'] = $courier_voucher_info['courier']; // Also include company name
}
```

---

## Complete Flow: From Order to API

### Step-by-Step Process

1. **Bulk Send Initiated**
   ```
   Admin clicks "Bulk Send" → ajax_bulk_send_orders() called
   ```

2. **Orders Retrieved**
   ```
   Query WooCommerce for pending orders (status: processing/completed, not sent yet)
   ```

3. **For Each Order** (in `process_order_robust()`):
   ```
   a. Check if already sent (idempotency check)
   b. Prepare order data (prepare_order_data())
      - Extract order details, customer info, shipping address
      - Call get_courier_voucher_from_order() to detect courier and voucher
      - Add courier/voucher to order data if found
   c. Send to API with idempotency key
   d. Handle response (success/failure)
   e. Update order meta (_dmm_delivery_sent, _dmm_delivery_order_id, etc.)
   ```

4. **API Payload Structure**
   ```json
   {
     "source": "woocommerce",
     "order": { ... },
     "customer": { ... },
     "shipping": { ... },
     "preferred_courier": "elta",        // If courier found
     "voucher_number": "123456789",      // If voucher found
     "courier_company": "elta",          // If voucher found
     "idempotency_key": "..."
   }
   ```

---

## Configuration

### Custom Meta Fields

Admins can configure custom meta field names for each courier in the plugin settings:

- `elta_voucher_meta_field` - Custom ELTA voucher meta field name
- `geniki_voucher_meta_field` - Custom Geniki voucher meta field name
- `acs_voucher_meta_field` - Custom ACS voucher meta field name

If configured, these take priority over the default field names.

### Settings Location

Settings are stored in WordPress options: `dmm_delivery_bridge_options`

---

## Important Notes

1. **Priority Order**: The first matching courier meta field wins. If an order has both ELTA and Geniki vouchers, ELTA will be selected (because it's checked first).

2. **Case Sensitivity**: Courier names are converted to lowercase before being sent to the API.

3. **Empty Values**: Empty strings, whitespace-only values, and non-existent meta fields are all treated as "not found".

4. **Courier-Voucher Relationship**: The voucher number is always associated with its courier. If a voucher is found, the courier is automatically determined from the meta field name.

5. **Fallback Behavior**: If no specific courier voucher is found, the system checks generic fields. However, generic fields may not always have a clear courier association.

---

## Troubleshooting

### Issue: Courier Not Detected

**Check:**
1. Verify the order has the correct meta field name (check WooCommerce order meta in database or admin)
2. Verify the meta field has a non-empty value
3. Check if custom meta field names are configured in settings
4. Check plugin logs for any errors during order processing

### Issue: Voucher Not Detected

**Check:**
1. Same as above (voucher uses same detection method)
2. Verify the meta field value is not just whitespace
3. Check if the value is stored correctly in WooCommerce order meta

### Issue: Wrong Courier Selected

**Check:**
1. Order may have multiple courier meta fields - the first one in priority order wins
2. Check the order meta fields to see which one is being matched
3. Consider adjusting the priority order in code if needed

---

## Code References

- **Main Detection Method**: `class-dmm-order-processor.php` → `get_courier_voucher_from_order()` (lines 712-775)
- **Data Preparation**: `class-dmm-order-processor.php` → `prepare_order_data()` (lines 502-684)
- **Bulk Processing**: `class-dmm-ajax-handlers.php` → `ajax_bulk_send_orders()` (lines 270-373)
- **Batch Processing**: `class-dmm-ajax-handlers.php` → `process_bulk_orders_batch()` (lines 549-582)

