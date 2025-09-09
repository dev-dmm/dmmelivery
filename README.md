# DM Delivery Bridge - WooCommerce Plugin

A WordPress/WooCommerce plugin that automatically sends orders to the DM Delivery tracking system.

## Features

- **Automatic Order Sending**: Automatically sends WooCommerce orders to your DM Delivery system when order status changes
- **Flexible Configuration**: Choose which order statuses trigger the sending
- **Manual Controls**: Send orders manually from the admin panel or individual order pages
- **Connection Testing**: Test your API connection before going live
- **Comprehensive Logging**: Track all API requests and responses for debugging
- **Error Handling**: Automatic retry functionality for failed requests
- **Order Status Tracking**: View DM Delivery status directly in WooCommerce order pages

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Active DM Delivery account with API access

## Installation

### Method 1: Manual Installation

1. Download the plugin file `dm-delivery-bridge.php`
2. Create a new folder called `dm-delivery-bridge` in your WordPress `/wp-content/plugins/` directory
3. Upload the `dm-delivery-bridge.php` file to this folder
4. Go to your WordPress admin panel → Plugins
5. Find "DM Delivery Bridge" and click "Activate"

### Method 2: Upload via WordPress Admin

1. Go to WordPress admin → Plugins → Add New
2. Click "Upload Plugin"
3. Choose the `dm-delivery-bridge.php` file
4. Click "Install Now" and then "Activate Plugin"

## Configuration

After installation, configure the plugin:

1. Go to **WooCommerce → DM Delivery** in your WordPress admin
2. Fill in the required settings:

### API Configuration

- **API Endpoint**: Your DM Delivery API URL (e.g., `https://oreksi.gr/api/woocommerce/order`)
- **API Key**: Your API key from the DM Delivery system
- **Tenant ID**: Your tenant ID from the DM Delivery system

### Behavior Settings

- **Auto Send Orders**: Enable/disable automatic order sending
- **Send on Order Status**: Choose which order statuses trigger sending (default: Processing, Completed)
- **Create Shipment**: Whether to automatically create shipments in DM Delivery
- **Debug Mode**: Enable detailed logging for troubleshooting

## Usage

### Automatic Mode

Once configured, the plugin will automatically:

1. Monitor WooCommerce orders for status changes
2. Send order data to DM Delivery when orders reach selected statuses
3. Log all activities for your review
4. Add order notes with success/failure information

### Manual Mode

You can also send orders manually:

1. **From Order Edit Page**: Look for the "DM Delivery Status" meta box on individual order pages
2. **Bulk Actions**: Use the logs section to resend failed orders
3. **Test Connection**: Use the "Test Connection" button to verify your setup

### Monitoring

Monitor plugin activity through:

- **Recent Logs**: View the last 20 API requests in the settings page
- **Order Notes**: Check individual orders for DM Delivery status updates
- **Order Meta Box**: See DM Delivery status directly on order edit pages

## Order Data Mapping

The plugin maps WooCommerce order data to DM Delivery format:

### Order Information
- Order ID → External Order ID
- Order Number → Order Number
- Order Status → Status
- Order Total → Total Amount
- Subtotal, Tax, Shipping, Discounts → Respective fields
- Currency → Currency
- Payment Method → Payment Method

### Customer Information
- Billing Name → Customer Name
- Billing Email → Customer Email
- Billing Phone → Customer Phone

### Shipping Information
- Shipping Address → Delivery Address
- Product Weights → Total Weight
- Falls back to billing address if shipping address is empty

## Troubleshooting

### Common Issues

1. **Orders Not Sending Automatically**
   - Check that "Auto Send Orders" is enabled
   - Verify the order status is in your selected statuses
   - Check Recent Logs for error messages

2. **Connection Test Fails**
   - Verify API endpoint URL is correct
   - Check API key and tenant ID are valid
   - Ensure your server can make outbound HTTP requests

3. **Orders Sent Multiple Times**
   - The plugin prevents duplicate sending by default
   - Check if you're manually resending already successful orders

### Debug Mode

Enable Debug Mode for detailed logging:

1. Go to WooCommerce → DM Delivery
2. Check "Debug Mode"
3. Save settings
4. Check Recent Logs for detailed request/response data

### Log Information

Each log entry contains:
- Order ID and timestamp
- Request data sent to API
- Response data from API
- Error messages (if any)
- Success/failure status

## API Response Handling

The plugin handles various API responses:

- **Success (200/201)**: Order marked as sent, shipment IDs stored
- **Client Errors (400-499)**: Logged as errors, manual retry available
- **Server Errors (500-599)**: Logged as errors, automatic retry possible
- **Network Errors**: Connection timeouts, DNS issues, etc.

## Security

The plugin implements several security measures:

- API keys are stored securely in WordPress options
- AJAX requests use WordPress nonces
- User permissions are checked for all admin actions
- Input validation on all configuration fields

## Support

For support with this plugin:

1. Check the Recent Logs for error details
2. Enable Debug Mode for more information
3. Verify your DM Delivery API credentials
4. Contact your DM Delivery system administrator

## Changelog

### Version 1.0.0
- Initial release
- Automatic order sending
- Manual order controls
- Connection testing
- Comprehensive logging
- Error handling and retry functionality

## License

This plugin is licensed under the GPL v2 or later.

---

**Note**: This plugin requires an active DM Delivery account and proper API credentials to function. Contact your DM Delivery system administrator for API access details.