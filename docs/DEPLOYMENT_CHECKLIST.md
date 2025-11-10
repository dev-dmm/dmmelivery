# Deployment Checklist - HMAC Signature System

Use this checklist before deploying the HMAC signature verification system to production.

## Pre-Deployment Verification

### 1. Environment Configuration

- [ ] `CACHE_DRIVER` is set to `redis`, `database`, or `memcached` (supports atomic locks)
- [ ] `QUEUE_CONNECTION` is configured (if using queues)
- [ ] `DM_BRIDGE_API_KEY` is set in `.env`
- [ ] `DM_BRIDGE_API_SECRET` is set in `.env`
- [ ] Server time is synchronized (NTP) for 5-minute timestamp window

### 2. Database Migrations

- [ ] Run `php artisan migrate` to apply:
  - `add_api_secret_to_tenants_table` - Adds `api_secret` field
  - `add_require_signed_webhooks_to_tenants_table` - Adds enforcement flag
  - `add_unique_keys_orders_shipments` - Adds unique constraints

### 3. Unique Constraints Verification

Verify unique indexes exist:
- [ ] `orders(tenant_id, external_order_id)` - Prevents duplicate orders
- [ ] `shipments(tenant_id, tracking_number)` - Prevents duplicate tracking numbers

```sql
-- Check indexes
SHOW INDEX FROM orders WHERE Key_name = 'orders_tenant_extid_unique';
SHOW INDEX FROM shipments WHERE Key_name = 'shipments_tenant_tracking_unique';
```

### 4. Middleware Configuration

- [ ] `woo.hmac` middleware is registered in `bootstrap/app.php`
- [ ] Middleware is applied to WooCommerce routes in `routes/api.php`
- [ ] Named rate limiter `woocommerce` is registered in `AppServiceProvider`
- [ ] Rate limiting is set to 60 requests/minute per tenant+IP

### 5. Code Verification

- [ ] `OrderItem` model has `'product_images' => 'array'` cast
- [ ] PII masking is active in validation error logs
- [ ] Lock provider check uses `LockProvider` interface
- [ ] Isolation level changes are wrapped in try-catch

### 6. WordPress Plugin Configuration

- [ ] Plugin is updated to send `X-Payload-Signature` header
- [ ] Plugin signs payloads using flexible format (timestamp/nonce/both)
- [ ] Plugin uses correct signature format (`sha256=<hex>` or `sha256=<base64>`)
- [ ] Plugin sends `X-Timestamp` and `X-Nonce` headers (recommended)

### 7. Testing

Run these tests before deployment:

#### Basic Auth Test
```bash
# Missing API key → 401
curl -X POST https://your-app.com/api/woocommerce/order \
  -H 'Content-Type: application/json' \
  -d '{"source":"woocommerce","order":{"external_order_id":"TEST1","total_amount":10},"shipping":{"address":{"address_1":"a","city":"c","postcode":"p"}}}'
```

#### HMAC Signature Test
```bash
# Use the cURL snippet from documentation
# Should return 200/201 with valid signature
# Should return 401 with invalid signature
```

#### Idempotency Test
```bash
# Send same order twice → first 201, second 200
# Verify no duplicate orders in database
```

#### Replay Protection Test
```bash
# Send request with same nonce twice within 10 minutes
# Second request should return 401 (replay detected)
```

### 8. Monitoring Setup

- [ ] Log aggregation configured (check for signature failures)
- [ ] Alerts set up for:
  - High rate of 401 responses
  - Signature verification failures
  - Replay attack attempts
  - Payload size violations

### 9. Rollout Strategy

- [ ] Start with `require_signed_webhooks = false` for all tenants
- [ ] Test with a few tenants first
- [ ] Gradually enable `require_signed_webhooks = true` per tenant
- [ ] Monitor error rates during rollout

### 10. Documentation

- [ ] WordPress plugin developers have access to `docs/WORDPRESS_PLUGIN_SIGNING.md`
- [ ] API documentation is updated with signature requirements
- [ ] Error codes are documented (401, 413, 415, 422, 423)

## Post-Deployment Verification

### Immediate Checks (within 1 hour)

- [ ] Check logs for any 500 errors
- [ ] Verify orders are being created successfully
- [ ] Check for any signature verification failures
- [ ] Monitor rate limiting (should see 429s if limits exceeded)

### First 24 Hours

- [ ] Review error logs for patterns
- [ ] Check for any PII leakage in logs
- [ ] Verify unique constraints are preventing duplicates
- [ ] Monitor cache performance (replay protection)

### First Week

- [ ] Review tenant feedback
- [ ] Check monthly shipment reset command ran successfully
- [ ] Verify no performance degradation
- [ ] Review security logs for suspicious activity

## Rollback Plan

If issues occur:

1. **Disable signature requirement**:
   ```sql
   UPDATE tenants SET require_signed_webhooks = false;
   ```

2. **Remove middleware** (temporary):
   - Comment out `'woo.hmac'` in `routes/api.php`

3. **Revert migrations** (if needed):
   ```bash
   php artisan migrate:rollback --step=3
   ```

## Support Contacts

- **Technical Issues**: [Your team contact]
- **WordPress Plugin**: [Plugin developer contact]
- **Security Concerns**: [Security team contact]

## Quick Reference

### Signature Formats Accepted
- `sha256=<hex>` (recommended)
- `sha256=<base64>`
- `<hex>` (without prefix)
- `<base64>` (without prefix)

### Error Codes
- `401` - Authentication/Signature failure
- `413` - Payload too large (>2MB)
- `415` - Invalid Content-Type
- `422` - Validation error
- `423` - Lock timeout (try again)
- `429` - Rate limit exceeded (returns JSON: `{"success": false, "message": "Rate limit exceeded. Please retry later.", "error_code": "RATE_LIMIT_EXCEEDED"}`)

### Rate Limits
- **60 requests per minute per tenant+IP** (named limiter `woocommerce`)
- Exceeding limit returns HTTP 429 with JSON response

### Payload Limits
- **Maximum size**: 2 MB
- **Content-Type**: `application/json` (charset allowed)

