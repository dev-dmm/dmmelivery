<?php
/**
 * ACS Courier integration page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('ACS Courier Integration', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Configure your ACS Courier API credentials to enable automatic shipment creation.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <?php 
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dmm-delivery-bridge-acs';
    DMM_Admin::render_navigation($current_page);
    ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('dmm_delivery_bridge_settings');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="acs_enabled"><?php _e('Enable ACS Courier', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="acs_enabled"
                               name="dmm_delivery_bridge_options[acs_enabled]" 
                               value="yes" 
                               <?php checked(isset($options['acs_enabled']) ? $options['acs_enabled'] : 'no', 'yes'); ?> />
                        <?php _e('Enable ACS Courier integration', 'dmm-delivery-bridge'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_api_endpoint"><?php _e('API Endpoint', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="acs_api_endpoint"
                           name="dmm_delivery_bridge_options[acs_api_endpoint]" 
                           value="<?php echo esc_attr(isset($options['acs_api_endpoint']) ? $options['acs_api_endpoint'] : 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest'); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('ACS Courier API endpoint URL', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_api_key"><?php _e('API Key', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="acs_api_key"
                           name="dmm_delivery_bridge_options[acs_api_key]" 
                           value="<?php echo esc_attr(isset($options['acs_api_key']) ? $options['acs_api_key'] : ''); ?>" 
                           class="regular-text" 
                           autocomplete="off" />
                    <p class="description"><?php _e('Your ACS Courier API key', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_company_id"><?php _e('Company ID', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="acs_company_id"
                           name="dmm_delivery_bridge_options[acs_company_id]" 
                           value="<?php echo esc_attr(isset($options['acs_company_id']) ? $options['acs_company_id'] : ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your ACS Courier company ID', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_company_password"><?php _e('Company Password', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="acs_company_password"
                           name="dmm_delivery_bridge_options[acs_company_password]" 
                           value="<?php echo esc_attr(isset($options['acs_company_password']) ? $options['acs_company_password'] : ''); ?>" 
                           class="regular-text" 
                           autocomplete="off" />
                    <p class="description"><?php _e('Your ACS Courier company password', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_user_id"><?php _e('User ID', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="acs_user_id"
                           name="dmm_delivery_bridge_options[acs_user_id]" 
                           value="<?php echo esc_attr(isset($options['acs_user_id']) ? $options['acs_user_id'] : ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your ACS Courier user ID', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_user_password"><?php _e('User Password', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="acs_user_password"
                           name="dmm_delivery_bridge_options[acs_user_password]" 
                           value="<?php echo esc_attr(isset($options['acs_user_password']) ? $options['acs_user_password'] : ''); ?>" 
                           class="regular-text" 
                           autocomplete="off" />
                    <p class="description"><?php _e('Your ACS Courier user password', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_voucher_meta_field"><?php _e('Voucher Meta Field', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <select 
                           id="acs_voucher_meta_field"
                           name="dmm_delivery_bridge_options[acs_voucher_meta_field]" 
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
        var $select = $('#acs_voucher_meta_field');
        var savedValue = '<?php echo esc_js(isset($options['acs_voucher_meta_field']) ? $options['acs_voucher_meta_field'] : ''); ?>';
        
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
                    
                    $.each(response.data.meta_fields, function(index, metaField) {
                        var selected = (metaField === savedValue) ? 'selected' : '';
                        $select.append('<option value="' + escapeHtml(metaField) + '" ' + selected + '>' + escapeHtml(metaField) + '</option>');
                    });
                }
            },
            error: function() {
                console.error('Failed to load meta fields');
            }
        });
    }
    
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    loadMetaFields();
});
</script>

