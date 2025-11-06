# Admin Views Directory

This directory contains the view templates for the DMM Delivery Bridge admin pages.

## View Files

The following view files should be created by extracting the HTML content from the original `dm-delivery-bridge.php` file:

- `settings-page.php` - Main settings page (admin_page method)
- `acs-page.php` - ACS Courier integration page (acs_admin_page method)
- `geniki-page.php` - Geniki Taxidromiki integration page (geniki_admin_page method)
- `elta-page.php` - ELTA Hellenic Post integration page (elta_admin_page method)
- `bulk-page.php` - Bulk processing page (bulk_admin_page method)
- `logs-page.php` - Error logs page (logs_admin_page method)
- `orders-page.php` - Orders management page (orders_admin_page method)
- `monitoring-page.php` - Monitoring page (monitoring_admin_page method)
- `log-details-page.php` - Log details page (log_details_admin_page method)

## Usage

Views are loaded using the `load_view()` method in the `DMM_Admin` class:

```php
$this->load_view('settings-page', [
    'options' => $this->options,
    'plugin' => $this->plugin
]);
```

## Extracting Views

To extract the views from the original file:

1. Find the admin page method in the original `dm-delivery-bridge.php` file
2. Copy the HTML/PHP content (everything between `?>` and the closing `<?php` or end of method)
3. Create a new file in this directory with the appropriate name
4. Wrap the content with proper PHP opening tag and security check
5. Use the variables passed from the `load_view()` method

## Example Structure

```php
<?php
/**
 * View name
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Your HTML content here -->
<div class="wrap">
    <h1><?php _e('Page Title', 'dmm-delivery-bridge'); ?></h1>
    <!-- ... rest of content ... -->
</div>
```

