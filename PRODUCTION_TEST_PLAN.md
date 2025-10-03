# DMM Delivery Bridge - Production Test Plan

## Pre-Deployment Checklist

### 1. HPOS Compatibility Test
```bash
# Enable HPOS in WooCommerce
wp option update woocommerce_custom_orders_table_enabled yes
wp option update woocommerce_custom_orders_table_data_sync_enabled yes

# Test order creation and processing
wp wc order create --status=processing --billing_first_name="Test" --billing_last_name="User" --billing_email="test@example.com"
```

### 2. Action Scheduler Health Check
```bash
# Check queue status
wp action-scheduler pending --group=dmm
wp action-scheduler run --group=dmm

# Monitor queue processing
wp action-scheduler status
```

### 3. Happy Path Test
1. Create test order in WooCommerce admin
2. Change status to `processing`
3. Verify:
   - Order appears in Action Scheduler queue
   - API call is made with proper headers
   - `_dmm_delivery_sent=yes` is set
   - Order note is added
   - Lock is cleared

### 4. Gateway Webhook Lag Test
1. Create order with payment gateway that sets `on-hold` status
2. Manually trigger `payment_complete` hook
3. Verify order is queued and sent correctly

### 5. Network Failure Simulation
```bash
# Simulate API endpoint down
curl -X POST http://your-api-endpoint --connect-timeout 1

# Verify retry schedule:
# - 2 minutes: First retry
# - 10 minutes: Second retry  
# - 1 hour: Third retry
# - 6 hours: Fourth retry
# - Final failure: Admin notification
```

### 6. Idempotency Test
1. Manually trigger the same Action Scheduler job twice
2. Verify:
   - Server returns 409/duplicate or 200
   - Client marks as sent only once
   - No duplicate orders in DMM system

### 7. Subscriptions Test
1. Create WooCommerce subscription
2. Generate renewal order
3. Verify exactly one enqueue and send (no duplicates)

### 8. Order Update Test
1. Send order successfully
2. Update billing/shipping address
3. Verify:
   - Order update is queued
   - PATCH/PUT request is made
   - Different idempotency key is used
   - Order note is added

### 9. Admin Monitoring Test
1. Access monitoring dashboard
2. Verify:
   - Queue status displays correctly
   - Recent activity shows properly
   - Failed orders list works
   - System health checks pass
   - CSRF protection works
   - "Retry" button re-enqueues (not synchronous)

### 10. Security Test
1. Verify API credentials are masked in admin UI
2. Check logs don't contain PII
3. Test payload signing works
4. Verify nonce protection on all AJAX actions

## Production Deployment Steps

### 1. Backup Current System
```bash
# Backup database
wp db export backup-before-dmm-bridge.sql

# Backup current plugin
cp -r wp-content/plugins/dmm-delivery-bridge wp-content/plugins/dmm-delivery-bridge-backup
```

### 2. Deploy Plugin
```bash
# Upload and activate plugin
wp plugin install dm-delivery-bridge.php --activate
```

### 3. Configure Settings
1. Set API endpoint, key, and tenant ID
2. Configure order statuses to trigger sending
3. Enable debug mode for initial testing
4. Set up monitoring dashboard

### 4. Test with Small Volume
1. Process 5-10 test orders
2. Monitor logs and queue status
3. Verify all orders reach DMM system
4. Check for any errors or duplicates

### 5. Monitor Production
1. Watch monitoring dashboard for 24 hours
2. Check error rates and retry patterns
3. Verify no performance impact on checkout
4. Monitor Action Scheduler queue health

## Rollback Plan

### If Issues Occur:
1. Deactivate plugin immediately
2. Restore from backup
3. Check for any stuck orders in queue
4. Clear any locks: `wp transient delete --all`
5. Investigate logs for root cause

## Performance Monitoring

### Key Metrics to Track:
- Queue processing time
- API response times
- Error rates
- Retry counts
- Memory usage during bulk operations

### Alerts to Set Up:
- High error rate (>10% in 1 hour)
- Queue backup (>100 pending jobs)
- API timeout rate (>5%)
- Memory usage spikes

## Security Considerations

### Data Protection:
- All PII is hashed in logs
- API credentials stored securely
- Payloads signed with HMAC
- CSRF protection on all actions

### Access Control:
- Admin functions require `manage_woocommerce` capability
- Nonce verification on all AJAX calls
- Rate limiting on API calls
- User-Agent validation on server side

## Maintenance Tasks

### Daily:
- Check monitoring dashboard
- Review error logs
- Monitor queue health

### Weekly:
- Review failed orders
- Check API performance
- Update retry policies if needed

### Monthly:
- Analyze error patterns
- Optimize retry schedules
- Review security logs
- Update documentation

## Troubleshooting Guide

### Common Issues:

1. **Orders not sending**
   - Check Action Scheduler is running
   - Verify API credentials
   - Check order status configuration

2. **Duplicate orders**
   - Verify idempotency keys are working
   - Check server-side duplicate detection
   - Review lock mechanisms

3. **High error rates**
   - Check API endpoint health
   - Verify network connectivity
   - Review retry policies

4. **Queue backup**
   - Check Action Scheduler status
   - Verify cron is running
   - Consider increasing queue workers

### Debug Commands:
```bash
# Check plugin status
wp plugin status dmm-delivery-bridge

# View recent logs
wp log list --type=error --limit=50

# Check Action Scheduler
wp action-scheduler status

# Test API connection
wp eval "DMM_Delivery_Bridge::getInstance()->ajax_test_connection()"
```

This test plan ensures the DMM Delivery Bridge is production-ready and can handle real-world scenarios safely and efficiently.
