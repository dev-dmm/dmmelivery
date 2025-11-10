# HMAC Signature System - Follow-up TODO List

This document tracks actionable follow-up tasks for the HMAC signature verification system.

## Documentation

- [ ] **Add `/api/woocommerce/ping` to README for integrators**
  - Document what it checks (auth, signature, rate limiter bucket)
  - Show expected JSON response format
  - Include example cURL request

## Environment Configuration

- [ ] **Verify `APP_TIMEZONE=Europe/Athens` in `.env`**
  - Ensure 03:00 scheduler aligns with monthly reset command
  - Confirm timezone is set correctly in production

## Monitoring & Alerts

- [ ] **Create dashboard counters for error codes**
  - Track 401 (Authentication/Signature failures)
  - Track 429 (Rate limit exceeded)
  - Track 415 (Invalid Content-Type)
  - Track 413 (Payload too large)
  - Track 423 (Lock timeout)

- [ ] **Set up alerts for security events**
  - Alert on spikes of `RATE_LIMIT_EXCEEDED` errors
  - Alert on spikes of `Invalid signature` errors
  - Alert on unusual patterns (potential attacks)

## Database Verification

- [ ] **Re-confirm unique indexes exist in production**
  - Verify `orders_tenant_extid_unique` on `orders(tenant_id, external_order_id)`
  - Verify `shipments_tenant_tracking_unique` on `shipments(tenant_id, tracking_number)`
  - Run: `SHOW INDEX FROM orders WHERE Key_name = 'orders_tenant_extid_unique';`
  - Run: `SHOW INDEX FROM shipments WHERE Key_name = 'shipments_tenant_tracking_unique';`

## Cache & Performance

- [ ] **Verify replay cache TTL under load**
  - Ensure 10-minute nonce TTL is sufficient
  - Confirm cache key namespace `hmac:replay:{tenant}:{nonce}` is working correctly
  - Monitor cache hit/miss rates
  - Consider cache warming strategies if needed

## Testing

- [ ] **Pest tests for core functionality**
  - [ ] Valid signature → 201 on first create, 200 on second (idempotency test)
  - [ ] Invalid signature → 401 with proper error message
  - [ ] Same nonce twice (within 10m) → second request returns 401 (replay protection)
  - [ ] Rate limit exceeded → 429 with JSON body containing `RATE_LIMIT_EXCEEDED`
  - [ ] Monthly reset command → simulate end-of-month (31→28) and verify effective day fallback
  - [x] `/ping` endpoint → returns correct tenant_id, IP, limiter_key, hmac_verified, and rate-limit info ✅

## Optional Enhancements

- [x] **Add `hmac_verified` flag to `/ping` response** ✅
  - Set request attribute in `VerifyWooHmac` middleware when signature is valid
  - Include `hmac_verified: true` in ping response for integrator debugging
  - `/ping` reflects `hmac_verified` and rate-limit fields in JSON for easier integrator debugging

- [ ] **Performance optimization**
  - Profile HMAC verification under high load
  - Consider caching tenant secrets (with invalidation on update)
  - Monitor database query performance for tenant lookups

## Deployment Verification

- [ ] **Post-deployment smoke tests**
  - Test `/ping` endpoint with valid credentials
  - Test order creation with valid signature
  - Test order update (PUT) with valid signature
  - Verify rate limiting is working per tenant+IP
  - Confirm monthly reset command runs successfully

## Documentation Updates

- [ ] **Update API documentation**
  - Add `/ping` endpoint to public API docs
  - Include rate limiting details in API reference
  - Document error response formats

- [ ] **Create integrator quick-start guide**
  - Step-by-step setup instructions
  - Common troubleshooting scenarios
  - Example code snippets for different languages

