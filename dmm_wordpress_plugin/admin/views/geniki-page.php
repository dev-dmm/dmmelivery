<?php
/**
 * Geniki Taxidromiki integration page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Geniki Taxidromiki Integration', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Configure your Geniki Taxidromiki API credentials to enable automatic shipment creation.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <?php 
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dmm-delivery-bridge-geniki';
    DMM_Admin::render_navigation($current_page);
    ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('dmm_delivery_bridge_settings');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="geniki_enabled"><?php _e('Enable Geniki', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="geniki_enabled"
                               name="dmm_delivery_bridge_options[geniki_enabled]" 
                               value="yes" 
                               <?php checked(isset($options['geniki_enabled']) ? $options['geniki_enabled'] : 'no', 'yes'); ?> />
                        <?php _e('Enable Geniki Taxidromiki integration', 'dmm-delivery-bridge'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="geniki_soap_endpoint"><?php _e('SOAP Endpoint', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="geniki_soap_endpoint"
                           name="dmm_delivery_bridge_options[geniki_soap_endpoint]" 
                           value="<?php echo esc_attr(isset($options['geniki_soap_endpoint']) ? $options['geniki_soap_endpoint'] : 'https://testvoucher.taxydromiki.gr/JobServicesV2.asmx?WSDL'); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Geniki Taxidromiki SOAP endpoint URL', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="geniki_username"><?php _e('Username', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="geniki_username"
                           name="dmm_delivery_bridge_options[geniki_username]" 
                           value="<?php echo esc_attr(isset($options['geniki_username']) ? $options['geniki_username'] : ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your Geniki Taxidromiki username', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="geniki_password"><?php _e('Password', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="geniki_password"
                           name="dmm_delivery_bridge_options[geniki_password]" 
                           value="<?php echo esc_attr(isset($options['geniki_password']) ? $options['geniki_password'] : ''); ?>" 
                           class="regular-text" 
                           autocomplete="off" />
                    <p class="description"><?php _e('Your Geniki Taxidromiki password', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="geniki_application_key"><?php _e('Application Key', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="geniki_application_key"
                           name="dmm_delivery_bridge_options[geniki_application_key]" 
                           value="<?php echo esc_attr(isset($options['geniki_application_key']) ? $options['geniki_application_key'] : ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your Geniki Taxidromiki application key', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="geniki_voucher_meta_field"><?php _e('Voucher Meta Field', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <select 
                           id="geniki_voucher_meta_field"
                           name="dmm_delivery_bridge_options[geniki_voucher_meta_field]" 
                           class="regular-text">
                        <option value=""><?php _e('-- Select Meta Field --', 'dmm-delivery-bridge'); ?></option>
                    </select>
                    <p class="description"><?php _e('Select which order meta field contains the voucher number', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Save Changes', 'dmm-delivery-bridge')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Load meta fields for voucher dropdown
    function loadMetaFields() {
        var $select = $('#geniki_voucher_meta_field');
        var savedValue = '<?php echo esc_js(isset($options['geniki_voucher_meta_field']) ? $options['geniki_voucher_meta_field'] : ''); ?>';
        
        $.ajax({
            url: dmmAdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'dmm_get_order_meta_fields',
                nonce: dmmAdminData.nonce
            },
            success: function(response) {
                if (response.success && response.data.meta_fields) {
                    $select.empty();
                    $select.append('<option value=""><?php echo esc_js(__('-- Select Meta Field --', 'dmm-delivery-bridge')); ?></option>');
                    
                    var foundSavedValue = false;
                    
                    // Add all meta fields from the response
                    $.each(response.data.meta_fields, function(index, metaField) {
                        var isSelected = (metaField === savedValue);
                        if (isSelected) {
                            foundSavedValue = true;
                        }
                        $select.append('<option value="' + escapeHtml(metaField) + '" ' + (isSelected ? 'selected' : '') + '>' + escapeHtml(metaField) + '</option>');
                    });
                    
                    // If saved value is not in the list, add it anyway (in case it was deleted from orders)
                    if (savedValue && !foundSavedValue) {
                        $select.append('<option value="' + escapeHtml(savedValue) + '" selected>' + escapeHtml(savedValue) + '</option>');
                    }
                    
                    // Ensure the saved value is selected
                    if (savedValue) {
                        $select.val(savedValue);
                    }
                }
            },
            error: function() {
                console.error('Failed to load meta fields');
                // If AJAX fails, try to set the saved value if it exists
                if (savedValue) {
                    $select.append('<option value="' + escapeHtml(savedValue) + '" selected>' + escapeHtml(savedValue) + '</option>');
                }
            }
        });
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    loadMetaFields();
});
</script>

