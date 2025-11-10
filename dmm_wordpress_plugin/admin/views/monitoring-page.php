<?php
/**
 * Monitoring page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Monitoring', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Monitor system performance, API health, and integration status.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <?php 
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dmm-delivery-bridge-monitoring';
    DMM_Admin::render_navigation($current_page);
    ?>
    
    <div class="dmm-monitoring-container">
        <!-- Circuit Breaker Status Card -->
        <div class="dmm-card" id="dmm-circuit-breaker-card" style="margin-bottom: 20px;">
            <div class="dmm-card-header">
                <h2 class="dmm-card-title"><?php _e('Circuit Breaker Status', 'dmm-delivery-bridge'); ?></h2>
                <button type="button" class="button button-secondary" id="dmm-refresh-circuit-breaker">
                    <?php _e('🔄 Refresh', 'dmm-delivery-bridge'); ?>
                </button>
            </div>
            <div class="dmm-card-content">
                <div id="dmm-circuit-breaker-loading" class="dmm-loading-container dmm-spinner-small" style="display: none;">
                    <div class="dmm-spinner"></div>
                    <p class="dmm-loading-message"><?php _e('Loading status...', 'dmm-delivery-bridge'); ?></p>
                </div>
                
                <div id="dmm-circuit-breaker-content">
                    <!-- Status will be loaded here via AJAX -->
                </div>
            </div>
        </div>
        
        <!-- Other Monitoring Content -->
        <div class="dmm-card">
            <div class="dmm-card-header">
                <h2 class="dmm-card-title"><?php _e('System Health', 'dmm-delivery-bridge'); ?></h2>
            </div>
            <div class="dmm-card-content">
                <p><?php _e('Additional monitoring functionality will be implemented here. This will display system health, API status, and performance metrics.', 'dmm-delivery-bridge'); ?></p>
                
                <p class="description">
                    <?php _e('Monitor API response times, success rates, error rates, and system resource usage.', 'dmm-delivery-bridge'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const nonce = window.dmmAdminNonce || '<?php echo wp_create_nonce('dmm_admin_nonce'); ?>';
        const loadingEl = document.getElementById('dmm-circuit-breaker-loading');
        const contentEl = document.getElementById('dmm-circuit-breaker-content');
        
        function formatTimeRemaining(seconds) {
            if (!seconds || seconds <= 0) return '';
            
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            
            if (minutes > 0) {
                return minutes + 'm ' + secs + 's';
            }
            return secs + 's';
        }
        
        function loadCircuitBreakerStatus() {
            loadingEl.style.display = 'flex';
            contentEl.innerHTML = '';
            
            const formData = new FormData();
            formData.append('action', 'dmm_get_circuit_breaker_status');
            formData.append('nonce', nonce);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loadingEl.style.display = 'none';
                
                if (data.success && data.data) {
                    const status = data.data;
                    
                    if (status.is_open) {
                        const timeRemaining = formatTimeRemaining(status.time_remaining);
                        const resetTime = status.until ? new Date(status.until * 1000).toLocaleString() : '';
                        
                        contentEl.innerHTML = `
                            <div class="dmm-message dmm-message-error" style="margin-bottom: 1rem;">
                                <p class="dmm-message-text">
                                    <strong><?php _e('⚠️ Circuit Breaker is OPEN', 'dmm-delivery-bridge'); ?></strong><br>
                                    <?php _e('API calls are currently disabled due to high error rate.', 'dmm-delivery-bridge'); ?>
                                </p>
                            </div>
                            
                            <table class="form-table">
                                <tr>
                                    <th><?php _e('Status:', 'dmm-delivery-bridge'); ?></th>
                                    <td><span class="dmm-status-badge dmm-status-badge-error"><?php _e('OPEN', 'dmm-delivery-bridge'); ?></span></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Reason:', 'dmm-delivery-bridge'); ?></th>
                                    <td>${status.message || '<?php _e('High error rate detected', 'dmm-delivery-bridge'); ?>'}</td>
                                </tr>
                                <tr>
                                    <th><?php _e('Auto-reset in:', 'dmm-delivery-bridge'); ?></th>
                                    <td id="dmm-time-remaining">${timeRemaining}</td>
                                </tr>
                                <tr>
                                    <th><?php _e('Auto-reset at:', 'dmm-delivery-bridge'); ?></th>
                                    <td>${resetTime}</td>
                                </tr>
                            </table>
                            
                            <div style="margin-top: 1.5rem;">
                                <button type="button" class="button button-primary" id="dmm-reset-circuit-breaker">
                                    <?php _e('🔓 Reset Circuit Breaker Now', 'dmm-delivery-bridge'); ?>
                                </button>
                                <p class="description" style="margin-top: 0.5rem;">
                                    <?php _e('⚠️ Only reset if you have resolved the underlying issue causing the errors.', 'dmm-delivery-bridge'); ?>
                                </p>
                            </div>
                        `;
                        
                        // Update countdown if time remaining
                        if (status.time_remaining && status.time_remaining > 0) {
                            const timeEl = document.getElementById('dmm-time-remaining');
                            let remaining = status.time_remaining;
                            
                            const countdown = setInterval(() => {
                                remaining--;
                                if (remaining <= 0) {
                                    clearInterval(countdown);
                                    loadCircuitBreakerStatus(); // Reload to get updated status
                                } else {
                                    timeEl.textContent = formatTimeRemaining(remaining);
                                }
                            }, 1000);
                        }
                        
                        // Add reset button handler
                        document.getElementById('dmm-reset-circuit-breaker').addEventListener('click', resetCircuitBreaker);
                    } else {
                        contentEl.innerHTML = `
                            <div class="dmm-message dmm-message-success">
                                <p class="dmm-message-text">
                                    <strong>✅ <?php _e('Circuit Breaker is CLOSED', 'dmm-delivery-bridge'); ?></strong><br>
                                    <?php _e('API calls are enabled and functioning normally.', 'dmm-delivery-bridge'); ?>
                                </p>
                            </div>
                        `;
                    }
                } else {
                    contentEl.innerHTML = `
                        <div class="dmm-message dmm-message-warning">
                            <p class="dmm-message-text">
                                <?php _e('Unable to load circuit breaker status.', 'dmm-delivery-bridge'); ?>
                            </p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                loadingEl.style.display = 'none';
                console.error('Error loading circuit breaker status:', error);
                contentEl.innerHTML = `
                    <div class="dmm-message dmm-message-error">
                        <p class="dmm-message-text">
                            <?php _e('Error loading circuit breaker status. Please try again.', 'dmm-delivery-bridge'); ?>
                        </p>
                    </div>
                `;
            });
        }
        
        function resetCircuitBreaker() {
            if (!confirm('<?php _e('Are you sure you want to reset the circuit breaker? This will re-enable API calls immediately.', 'dmm-delivery-bridge'); ?>')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'dmm_reset_circuit_breaker');
            formData.append('nonce', nonce);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.data.message || '<?php _e('Circuit breaker has been reset.', 'dmm-delivery-bridge'); ?>');
                    loadCircuitBreakerStatus();
                } else {
                    alert(data.data?.message || '<?php _e('Failed to reset circuit breaker.', 'dmm-delivery-bridge'); ?>');
                }
            })
            .catch(error => {
                console.error('Error resetting circuit breaker:', error);
                alert('<?php _e('Error resetting circuit breaker. Please try again.', 'dmm-delivery-bridge'); ?>');
            });
        }
        
        // Load status on page load
        loadCircuitBreakerStatus();
        
        // Refresh button
        document.getElementById('dmm-refresh-circuit-breaker').addEventListener('click', loadCircuitBreakerStatus);
        
        // Auto-refresh every 30 seconds
        setInterval(loadCircuitBreakerStatus, 30000);
    })();
    </script>
</div>

