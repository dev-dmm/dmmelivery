<?php
/**
 * ELTA Hellenic Post integration page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('ELTA Hellenic Post Integration', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Configure your ELTA Hellenic Post API credentials to enable automatic shipment creation.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <?php 
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dmm-delivery-bridge-elta';
    DMM_Admin::render_navigation($current_page);
    ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('dmm_delivery_bridge_settings');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="elta_enabled"><?php _e('Enable ELTA', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="elta_enabled"
                               name="dmm_delivery_bridge_options[elta_enabled]" 
                               value="yes" 
                               <?php checked(isset($options['elta_enabled']) ? $options['elta_enabled'] : 'no', 'yes'); ?> />
                        <?php _e('Enable ELTA Hellenic Post integration', 'dmm-delivery-bridge'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="elta_api_endpoint"><?php _e('API Endpoint', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="elta_api_endpoint"
                           name="dmm_delivery_bridge_options[elta_api_endpoint]" 
                           value="<?php echo esc_attr(isset($options['elta_api_endpoint']) ? $options['elta_api_endpoint'] : 'https://customers.elta-courier.gr'); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('ELTA Hellenic Post API endpoint URL', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="elta_user_code"><?php _e('User Code', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="elta_user_code"
                           name="dmm_delivery_bridge_options[elta_user_code]" 
                           value="<?php echo esc_attr(isset($options['elta_user_code']) ? $options['elta_user_code'] : ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your ELTA user code', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="elta_user_pass"><?php _e('User Password', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="elta_user_pass"
                           name="dmm_delivery_bridge_options[elta_user_pass]" 
                           value="<?php echo esc_attr(isset($options['elta_user_pass']) ? $options['elta_user_pass'] : ''); ?>" 
                           class="regular-text" 
                           autocomplete="off" />
                    <p class="description"><?php _e('Your ELTA user password', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="elta_apost_code"><?php _e('Apost Code', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="elta_apost_code"
                           name="dmm_delivery_bridge_options[elta_apost_code]" 
                           value="<?php echo esc_attr(isset($options['elta_apost_code']) ? $options['elta_apost_code'] : ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your ELTA apost code', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="elta_voucher_meta_field"><?php _e('Voucher Meta Field', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <select 
                           id="elta_voucher_meta_field"
                           name="dmm_delivery_bridge_options[elta_voucher_meta_field]" 
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
        var $select = $('#elta_voucher_meta_field');
        var savedValue = '<?php echo esc_js(isset($options['elta_voucher_meta_field']) ? $options['elta_voucher_meta_field'] : ''); ?>';
        
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

