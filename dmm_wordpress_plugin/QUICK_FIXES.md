# Quick Security Fixes - Immediate Action Required

## 🔴 Critical SQL Injection Fixes

### Fix 1: Line 1083 - Unsafe Query
**Current Code:**
```php
$logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 20");
```

**Fixed Code:**
```php
$table_name = $wpdb->prefix . 'dmm_delivery_logs';
$logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM `{$table_name}` ORDER BY created_at DESC LIMIT %d",
    20
));
```

### Fix 2: Line 2989 - Unsafe Query
**Current Code:**
```php
$orders = $wpdb->get_results($query);
```

**Fixed Code:**
```php
$query = $wpdb->prepare("
    SELECT 
        p.ID as order_id,
        p.post_date as order_date
    FROM {$wpdb->posts} p
    WHERE p.post_type = 'shop_order'
    ORDER BY p.ID DESC
    LIMIT %d
", 50);
$orders = $wpdb->get_results($query);
```

### Fix 3: Line 5053 - Unsafe Query
**Current Code:**
```php
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
```

**Fixed Code:**
```php
$table_name = $wpdb->prefix . 'dmm_delivery_logs';
$table_exists = $wpdb->get_var($wpdb->prepare(
    "SHOW TABLES LIKE %s",
    $table_name
)) === $table_name;
```

### Fix 4: Line 10474 - Unsafe TRUNCATE
**Current Code:**
```php
$result = $wpdb->query("TRUNCATE TABLE $table_name");
```

**Fixed Code:**
```php
$table_name = $wpdb->prefix . 'dmm_delivery_logs';
// Validate table name to prevent injection
if (preg_match('/^[a-zA-Z0-9_]+$/', str_replace($wpdb->prefix, '', $table_name))) {
    $result = $wpdb->query("TRUNCATE TABLE `{$table_name}`");
} else {
    wp_send_json_error(['message' => 'Invalid table name']);
}
```

### Fix 5: Line 10501 - Unsafe Query
**Current Code:**
```php
$logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
```

**Fixed Code:**
```php
$table_name = $wpdb->prefix . 'dmm_delivery_logs';
$logs = $wpdb->get_results("SELECT * FROM `{$table_name}` ORDER BY created_at DESC");
```

---

## 🟡 Remove Duplicate AJAX Handlers

### Remove Lines 284-290 (Duplicates of 158-164)
**Delete these duplicate registrations:**
```php
// REMOVE THESE LINES (284-290):
// ACS Courier AJAX handlers
add_action('wp_ajax_dmm_acs_track_shipment', [$this, 'ajax_acs_track_shipment']);
add_action('wp_ajax_dmm_acs_create_voucher', [$this, 'ajax_acs_create_voucher']);
add_action('wp_ajax_dmm_acs_calculate_price', [$this, 'ajax_acs_calculate_price']);
add_action('wp_ajax_dmm_acs_validate_address', [$this, 'ajax_acs_validate_address']);
add_action('wp_ajax_dmm_acs_find_stations', [$this, 'ajax_acs_find_stations']);
add_action('wp_ajax_dmm_acs_test_connection', [$this, 'ajax_acs_test_connection']);
```

**Keep the ones on lines 158-164** (they're registered first).

---

## 🟡 Remove Debug Output

### Fix 1: Line 8754 - Remove print_r
**Current Code:**
```php
error_log('ACS Tracking Response: ' . print_r($response, true));
```

**Fixed Code:**
```php
if ($this->is_debug_mode()) {
    error_log('ACS Tracking Response: ' . wp_json_encode($response, JSON_PRETTY_PRINT));
}
```

### Fix 2: Line 8758 - Remove print_r
**Current Code:**
```php
error_log('Parsed Events: ' . print_r($events, true));
```

**Fixed Code:**
```php
if ($this->is_debug_mode()) {
    error_log('Parsed Events: ' . wp_json_encode($events, JSON_PRETTY_PRINT));
}
```

### Fix 3: Line 5386 - Remove print_r
**Current Code:**
```php
error_log('DMM Delivery Bridge - Progress after init: ' . print_r($progress, true));
```

**Fixed Code:**
```php
if ($this->is_debug_mode()) {
    error_log('DMM Delivery Bridge - Progress after init: ' . wp_json_encode($progress, JSON_PRETTY_PRINT));
}
```

**Add helper method if it doesn't exist:**
```php
private function is_debug_mode() {
    return isset($this->options['debug_mode']) && $this->options['debug_mode'] === 'yes';
}
```

---

## 🟢 Clean Up Debug Log File

### Immediate Action:
1. **Archive the log file:**
   ```bash
   mv "debug (1).log" "debug_archive_$(date +%Y%m%d).log"
   ```

2. **Add to .gitignore:**
   ```
   *.log
   debug*.log
   ```

3. **Add log rotation in cleanup method:**
   ```php
   public function cleanup_old_logs() {
       $log_file = DMM_DELIVERY_BRIDGE_PLUGIN_DIR . 'debug (1).log';
       if (file_exists($log_file) && filesize($log_file) > 10 * 1024 * 1024) { // 10MB
           // Archive old log
           $archive = DMM_DELIVERY_BRIDGE_PLUGIN_DIR . 'debug_archive_' . date('Y-m-d') . '.log';
           rename($log_file, $archive);
           
           // Keep only last 7 days of archives
           $files = glob(DMM_DELIVERY_BRIDGE_PLUGIN_DIR . 'debug_archive_*.log');
           foreach ($files as $file) {
               if (filemtime($file) < strtotime('-7 days')) {
                   @unlink($file);
               }
           }
       }
   }
   ```

---

## 🔒 Standardize Nonce Verification

### Create Helper Method (add to class):
```php
/**
 * Verify AJAX request with nonce and capabilities
 */
private function verify_ajax_request($action, $capability = 'manage_options') {
    check_ajax_referer($action, 'nonce');
    
    $capabilities = ['manage_options'];
    if ($capability === 'manage_woocommerce' || $capability === 'both') {
        $capabilities[] = 'manage_woocommerce';
    }
    
    $has_cap = false;
    foreach ($capabilities as $cap) {
        if (current_user_can($cap)) {
            $has_cap = true;
            break;
        }
    }
    
    if (!$has_cap) {
        wp_send_json_error(['message' => 'Insufficient permissions'], 403);
    }
}
```

### Update AJAX Handlers to Use Helper:
**Example - Line 4683:**
```php
public function ajax_resend_order() {
    $this->verify_ajax_request('dmm_resend_order');
    
    $order_id = intval($_POST['order_id'] ?? 0);
    // ... rest of method
}
```

---

## 📋 Testing Checklist

After applying fixes, test:
- [ ] All AJAX endpoints still work
- [ ] No PHP errors in debug.log
- [ ] Database queries execute correctly
- [ ] No duplicate AJAX handler warnings
- [ ] Log rotation works
- [ ] Debug mode properly gates output

---

## ⚠️ Important Notes

1. **Backup before making changes**
2. **Test in staging first**
3. **Apply fixes incrementally**
4. **Monitor error logs after deployment**
5. **Keep original code commented for rollback**

---

*Priority: Apply these fixes within 24-48 hours*

