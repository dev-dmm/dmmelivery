<?php
/**
 * Log details page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get log ID from query string
$log_id = isset($_GET['log_id']) ? absint($_GET['log_id']) : 0;

// Fetch log data from database
$log_data = null;
if ($log_id > 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dmm_delivery_logs';
    
    $log_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $log_id
        ),
        ARRAY_A
    );
}

// Parse JSON data
$request_data = null;
$response_data = null;
if ($log_data) {
    if (!empty($log_data['request_data'])) {
        $request_data = json_decode($log_data['request_data'], true);
    }
    if (!empty($log_data['response_data'])) {
        $response_data = json_decode($log_data['response_data'], true);
    }
}
?>

<div class="wrap dmm-admin-page">
    <h1><?php _e('Log Details', 'dmm-delivery-bridge'); ?></h1>
    
    <!-- Navigation Links -->
    <?php 
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dmm-delivery-bridge-log-details';
    DMM_Admin::render_navigation('dmm-delivery-bridge-logs'); // Highlight logs page since this is a sub-page
    ?>
    
    <div class="dmm-log-details-container">
        <?php if ($log_id > 0 && $log_data): ?>
            <!-- Log Summary Card -->
            <div class="dmm-card">
                <div class="dmm-card-header">
                    <h2 class="dmm-card-title">
                        <?php printf(__('Log Entry #%d', 'dmm-delivery-bridge'), $log_id); ?>
                    </h2>
                    <div>
                        <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-logs'); ?>" class="button button-secondary">
                            <?php _e('← Back to Logs', 'dmm-delivery-bridge'); ?>
                        </a>
                    </div>
                </div>
                <div class="dmm-card-content">
                    <table class="dmm-admin-table" style="margin-bottom: 1.5rem;">
                        <tbody>
                            <tr>
                                <th style="width: 200px;"><?php _e('Log ID', 'dmm-delivery-bridge'); ?></th>
                                <td><strong>#<?php echo esc_html($log_data['id']); ?></strong></td>
                            </tr>
                            <tr>
                                <th><?php _e('Order ID', 'dmm-delivery-bridge'); ?></th>
                                <td>
                                    <?php if ($log_data['order_id'] > 0): ?>
                                        <a href="<?php echo admin_url('post.php?post=' . $log_data['order_id'] . '&action=edit'); ?>" target="_blank">
                                            #<?php echo esc_html($log_data['order_id']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #646970;"><?php _e('N/A', 'dmm-delivery-bridge'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Status', 'dmm-delivery-bridge'); ?></th>
                                <td>
                                    <?php
                                    $status = $log_data['status'];
                                    $status_class = 'dmm-status-badge-' . ($status === 'error' ? 'error' : ($status === 'warning' ? 'warning' : ($status === 'success' ? 'success' : 'info')));
                                    ?>
                                    <span class="dmm-status-badge <?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html(strtoupper($status)); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Context', 'dmm-delivery-bridge'); ?></th>
                                <td><?php echo esc_html($log_data['context'] ?? 'api'); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Timestamp', 'dmm-delivery-bridge'); ?></th>
                                <td><?php echo esc_html($log_data['created_at']); ?></td>
                            </tr>
                            <?php if (!empty($log_data['error_message'])): ?>
                            <tr>
                                <th><?php _e('Error Message', 'dmm-delivery-bridge'); ?></th>
                                <td>
                                    <div class="dmm-message dmm-message-error" style="margin: 0;">
                                        <p class="dmm-message-text"><?php echo esc_html($log_data['error_message']); ?></p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Request Data Card -->
            <?php if ($request_data): ?>
            <div class="dmm-card">
                <div class="dmm-card-header">
                    <h2 class="dmm-card-title"><?php _e('Request Data', 'dmm-delivery-bridge'); ?></h2>
                </div>
                <div class="dmm-card-content">
                    <details open>
                        <summary style="cursor: pointer; font-weight: 600; margin-bottom: 1rem; color: #2271b1;">
                            <?php _e('View Request Data', 'dmm-delivery-bridge'); ?>
                        </summary>
                        <pre style="background: #f6f7f7; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html(wp_json_encode($request_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
                    </details>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Response Data Card -->
            <?php if ($response_data): ?>
            <div class="dmm-card">
                <div class="dmm-card-header">
                    <h2 class="dmm-card-title"><?php _e('Response Data', 'dmm-delivery-bridge'); ?></h2>
                </div>
                <div class="dmm-card-content">
                    <?php if (isset($response_data['http_code']) && $response_data['http_code'] >= 500): ?>
                        <div class="dmm-message dmm-message-error" style="margin-bottom: 1rem;">
                            <p class="dmm-message-text">
                                <strong><?php _e('Server Error:', 'dmm-delivery-bridge'); ?></strong> 
                                <?php _e('The API server returned an HTTP 500 error. This indicates a problem on the server side, not with the request data. Check the API server logs for more details.', 'dmm-delivery-bridge'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($response_data['response_body']) && !empty($response_data['response_body'])): ?>
                        <div style="margin-bottom: 1rem;">
                            <strong><?php _e('Raw Response Body:', 'dmm-delivery-bridge'); ?></strong>
                            <pre style="background: #f6f7f7; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 12px; line-height: 1.5; margin-top: 0.5rem;"><?php echo esc_html($response_data['response_body']); ?></pre>
                        </div>
                    <?php endif; ?>
                    
                    <details open>
                        <summary style="cursor: pointer; font-weight: 600; margin-bottom: 1rem; color: #2271b1;">
                            <?php _e('View Response Data', 'dmm-delivery-bridge'); ?>
                        </summary>
                        <pre style="background: #f6f7f7; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html(wp_json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
                    </details>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Raw Data Card (for debugging) -->
            <div class="dmm-card">
                <div class="dmm-card-header">
                    <h2 class="dmm-card-title"><?php _e('Raw Log Data', 'dmm-delivery-bridge'); ?></h2>
                </div>
                <div class="dmm-card-content">
                    <details>
                        <summary style="cursor: pointer; font-weight: 600; margin-bottom: 1rem; color: #646970;">
                            <?php _e('View Raw Data (for debugging)', 'dmm-delivery-bridge'); ?>
                        </summary>
                        <pre style="background: #f6f7f7; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html(print_r($log_data, true)); ?></pre>
                    </details>
                </div>
            </div>
            
        <?php elseif ($log_id > 0 && !$log_data): ?>
            <div class="notice notice-error">
                <p><?php printf(__('Log entry #%d not found in the database.', 'dmm-delivery-bridge'), $log_id); ?></p>
            </div>
            <p>
                <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-logs'); ?>" class="button">
                    <?php _e('← Back to Logs', 'dmm-delivery-bridge'); ?>
                </a>
            </p>
        <?php else: ?>
            <div class="notice notice-error">
                <p><?php _e('Invalid log ID. Please select a log entry from the logs page.', 'dmm-delivery-bridge'); ?></p>
            </div>
            <p>
                <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-logs'); ?>" class="button">
                    <?php _e('← Back to Logs', 'dmm-delivery-bridge'); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</div>

