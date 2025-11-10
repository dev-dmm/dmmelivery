# WordPress Plugin HMAC Signature Contract

This document describes the exact signing protocol for requests from the WordPress plugin to the DMM Delivery API.

## Overview

All requests from the WordPress plugin to the WooCommerce endpoints must include HMAC-SHA256 signatures for security. The signature verifies:
- **Authenticity**: The request comes from a legitimate source
- **Integrity**: The payload hasn't been tampered with
- **Replay protection**: Prevents attackers from reusing captured requests

## Required Headers

All requests must include these headers:

```
Content-Type: application/json (charset allowed, e.g., application/json; charset=utf-8)
  Note: Required for POST/PUT, optional for GET/HEAD
X-Api-Key: <tenant-token-or-global-bridge-key>
X-Tenant-Id: <tenant-uuid>
X-Timestamp: <unix-epoch-seconds>  (optional but recommended for replay protection)
X-Nonce: <random-16-bytes-hex>     (optional but recommended for replay protection)
X-Payload-Signature: sha256=<hex-signature> OR sha256=<base64-signature> OR <hex> OR <base64>
```

**Note**: Payload size must be ≤ 2 MB. Requests exceeding this limit will be rejected with HTTP 413.

## Signature Format

The server accepts signatures in multiple formats for interoperability:

1. **Hex format (recommended)**: `sha256=<64-character-hex-string>`
2. **Base64 format**: `sha256=<base64-encoded-signature>`
3. **Plain hex**: `<64-character-hex-string>` (without prefix)
4. **Plain base64**: `<base64-encoded-signature>` (without prefix)

All formats are case-insensitive.

## Signing Process

### Step 1: Prepare the Payload

```php
$payload = [
    'source' => 'woocommerce',
    'order' => [
        'external_order_id' => '12345',
        'total_amount' => 99.99,
        // ... other order data
    ],
    'customer' => [
        // ... customer data
    ],
    'shipping' => [
        // ... shipping data
    ]
];

// Encode to JSON (must match exactly what's sent in body)
$body = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
```

### Step 2: Generate Timestamp and Nonce (Recommended)

```php
$timestamp = time(); // Unix epoch seconds
$nonce = bin2hex(random_bytes(16)); // 32-character hex string
```

### Step 3: Create the Signed String

The signed string format is flexible - include whichever headers are present:

- **Both timestamp and nonce**: `"{timestamp}.{nonce}.{body}"`
- **Only timestamp**: `"{timestamp}.{body}"`
- **Only nonce**: `"{nonce}.{body}"`
- **Neither**: `"{body}"`

```php
$pieces = [];
if ($timestamp > 0) {
    $pieces[] = (string) $timestamp;
}
if ($nonce !== '') {
    $pieces[] = (string) $nonce;
}

if (!empty($pieces)) {
    $signedString = implode('.', $pieces) . '.' . $body;
} else {
    $signedString = $body;
}
```

### Step 4: Compute HMAC Signature

Get the secret (tenant-specific or global bridge secret):

```php
$secret = $tenantApiSecret ?? $globalBridgeSecret;
```

Compute the signature (hex format - recommended):

```php
$signature = hash_hmac('sha256', $signedString, $secret);
$signatureHeader = "sha256={$signature}";
```

Or base64 format:

```php
$signature = base64_encode(hash_hmac('sha256', $signedString, $secret, true));
$signatureHeader = "sha256={$signature}";
```

### Step 5: Send Request

```php
$headers = [
    'Content-Type'         => 'application/json',
    'X-Api-Key'            => $apiKey,
    'X-Tenant-Id'          => $tenantId,
    'X-Timestamp'          => (string) $timestamp,
    'X-Nonce'              => $nonce,
    'X-Payload-Signature'  => $signatureHeader,
];

// POST for creating orders
$response = wp_remote_post('https://your-api.com/api/woocommerce/order', [
    'headers' => $headers,
    'body'    => $body,  // Raw JSON string (not the array!)
    'timeout' => 15,
]);

// PUT for updating orders (idempotent sync)
// $response = wp_remote_request('https://your-api.com/api/woocommerce/order', [
//     'method'  => 'PUT',
//     'headers' => $headers,
//     'body'    => $body,
//     'timeout' => 15,
// ]);
```

## Complete Example

```php
function send_order_to_dmm($order_data, $api_key, $tenant_id, $api_secret) {
    // 1. Prepare payload
    $payload = [
        'source' => 'woocommerce',
        'order' => [
            'external_order_id' => $order_data['id'],
            'total_amount' => $order_data['total'],
            // ... rest of order data
        ],
        // ... customer, shipping data
    ];
    
    // 2. Encode to JSON
    $body = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
    
    // 3. Generate timestamp and nonce
    $timestamp = time();
    $nonce = bin2hex(random_bytes(16));
    
    // 4. Create signed string (flexible - include whichever is present)
    $pieces = [];
    if ($timestamp > 0) {
        $pieces[] = (string) $timestamp;
    }
    if ($nonce !== '') {
        $pieces[] = (string) $nonce;
    }
    
    if (!empty($pieces)) {
        $signedString = implode('.', $pieces) . '.' . $body;
    } else {
        $signedString = $body;
    }
    
    // 5. Compute signature
    $signature = hash_hmac('sha256', $signedString, $api_secret);
    $signatureHeader = "sha256={$signature}";
    
    // 6. Prepare headers
    $headers = [
        'Content-Type'         => 'application/json',
        'X-Api-Key'            => $api_key,
        'X-Tenant-Id'          => $tenant_id,
        'X-Timestamp'          => (string) $timestamp,
        'X-Nonce'              => $nonce,
        'X-Payload-Signature'  => $signatureHeader,
    ];
    
    // 7. Send request
    $response = wp_remote_post('https://your-api.com/api/woocommerce/order', [
        'headers' => $headers,
        'body'    => $body,
        'timeout' => 15,
    ]);
    
    return $response;
}
```

## Security Notes

1. **Never log the secret**: The API secret should never appear in logs or error messages.

2. **Use HTTPS only**: Always send requests over HTTPS to protect the secret in transit.

3. **Rotate secrets regularly**: Change API secrets periodically for enhanced security.

4. **Store secrets securely**: Use WordPress options API with encryption or environment variables.

5. **Replay protection**: The timestamp and nonce prevent replay attacks:
   - Timestamp must be within 5 minutes of server time (if provided)
   - Nonce must be unique (server caches used nonces for ~10 minutes, if provided)
   - **Nonce TTL**: Do not reuse nonces within the 10-minute window
   - **Either timestamp OR nonce alone** suffices for replay protection, but **both together** is best practice

6. **Rate limiting**: Endpoints are rate-limited to **60 requests per minute per tenant+IP** (named limiter `woocommerce`). Exceeding this limit returns HTTP 429 with a JSON error response.

7. **Content-Type validation**: Only `application/json` (with optional charset) is accepted. Other types return HTTP 415.

8. **Payload size**: Maximum payload size is 2 MB. Larger payloads return HTTP 413.

## Error Responses

If signature verification fails, the server returns:

```json
{
    "success": false,
    "message": "Invalid signature"
}
```

HTTP Status: `401 Unauthorized`

## Tenant Configuration

Tenants can require signatures for all requests by setting `require_signed_webhooks = true` in the database. When enabled:
- Requests without `X-Payload-Signature` header are rejected
- Invalid signatures are rejected
- This allows gradual rollout: start with optional signatures, then enforce per-tenant

## Testing

### Testing Signature Generation

To test signature generation:

```php
$testPayload = ['test' => 'data'];
$body = wp_json_encode($testPayload, JSON_UNESCAPED_SLASHES);
$timestamp = time();
$nonce = bin2hex(random_bytes(16));
$signed = "{$timestamp}.{$nonce}.{$body}";
$secret = 'your-secret-here';
$signature = hash_hmac('sha256', $signed, $secret);

echo "Signature: sha256={$signature}\n";
echo "Headers to send:\n";
echo "X-Timestamp: {$timestamp}\n";
echo "X-Nonce: {$nonce}\n";
echo "X-Payload-Signature: sha256={$signature}\n";
```

### Testing `/ping` Endpoint

The `/ping` endpoint helps verify your integration setup. Since `GET /ping` has an empty body, sign `"timestamp.nonce."` (timestamp + nonce + dot + empty string):

```bash
# Replace values; ensure you sign exactly as per docs
# Note: Content-Type header is optional for GET/HEAD requests
curl -i -X GET "https://your-api.com/api/woocommerce/ping" \
  -H "X-Api-Key: <tenant-or-bridge-key>" \
  -H "X-Tenant-Id: <tenant-uuid>" \
  -H "X-Timestamp: $(date +%s)" \
  -H "X-Nonce: $(openssl rand -hex 16)" \
  -H "X-Payload-Signature: sha256=<signature-of-timestamp.nonce.\"\">"
```

**HTTP Methods**: Both `GET /api/woocommerce/ping` and `HEAD /api/woocommerce/ping` are supported. `HEAD` behaves exactly like `GET` (no body, same signing with empty body if signature is sent).

**Signature Format Examples**:
- Hex format (recommended): `X-Payload-Signature: sha256=a1b2c3d4e5f6...` (64-character hex string)
- Base64 format: `X-Payload-Signature: sha256=obLD08Pz...` (base64-encoded signature)

**Note**: 
- Calling with *no* signature should return 401 if `require_signed_webhooks=true` for your tenant
- The response includes `hmac_verified: true` when signature validation succeeds (or `false` if no signature was provided)
- Rate-limit information is included in the `ratelimit` object
- `ratelimit.remaining` shows the count **after** the current request (since the throttle middleware runs before the route)
- **All WooCommerce API responses include standard rate-limit headers** (`X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`, `X-RateLimit-Reset`) for easy monitoring

## Troubleshooting

### Signature verification fails

1. **Check JSON encoding**: Ensure `JSON_UNESCAPED_SLASHES` flag is used
2. **Verify secret**: Confirm the secret matches between plugin and server
3. **Check timestamp**: Ensure server time is synchronized (within 5 minutes)
4. **Verify format**: Use `sha256=` prefix with hex signature
5. **Check payload**: Ensure the exact same JSON string is signed and sent

### Replay errors

- Each nonce can only be used once
- Wait 10 minutes before reusing a nonce (or use a new one)
- Ensure timestamp is current (within 5 minutes)

## Production Readiness Checklist

Before deploying to production, verify:

### Infrastructure Configuration

- **Trusted Proxies**: If behind Cloudflare/Nginx, configure `TRUSTED_PROXIES` (or `App\Http\Middleware\TrustProxies`) so rate limiter keys use the real client IP, not the proxy. Otherwise all tenants may share rate-limit buckets.

- **Clock Synchronization**: Ensure NTP is enabled on app servers (and WordPress nodes) to keep the ±5-minute timestamp window reliable.

- **Config Cache**: Run `php artisan config:cache` in staging/prod so `config('rate.*')` is fast and consistent.

- **Tenant Cache**: Tenant lookups are cached for 5 minutes. If API secrets rotate, invalidate the cache with `Cache::forget("tenant:{$tenantId}")` or clear the entire cache.

### CORS Configuration

- If using Laravel CORS package, ensure it's properly configured
- The catch-all `OPTIONS` handler works for preflight, but main CORS middleware should set `Access-Control-*` headers

### Testing HEAD Requests

Example cURL for `HEAD /api/woocommerce/ping` with hex signature:

```bash
TS=$(date +%s)
NONCE=$(openssl rand -hex 16)
SIGNED="${TS}.${NONCE}."

# Hex signature (recommended)
SIG=$(printf "%s" "$SIGNED" | openssl dgst -sha256 -hmac "$API_SECRET" -r | awk '{print $1}')

# Base64 signature (alternative)
SIG_B64=$(printf "%s" "$SIGNED" | openssl dgst -sha256 -mac HMAC -macopt "key:$API_SECRET" -binary | base64)

curl -i -X HEAD "https://your-api.com/api/woocommerce/ping" \
  -H "X-Api-Key: $API_KEY" \
  -H "X-Tenant-Id: $TENANT_ID" \
  -H "X-Timestamp: $TS" \
  -H "X-Nonce: $NONCE" \
  -H "X-Payload-Signature: sha256=$SIG"
  # Or use base64: -H "X-Payload-Signature: sha256=$SIG_B64"
```

### Smoke Tests (Run in Staging)

1. **Rate Limiting**: `GET /api/woocommerce/ping` 3x quickly → last call returns 429; confirm headers + JSON `RATE_LIMIT_EXCEEDED`.

2. **HEAD Support**: `HEAD /api/woocommerce/ping` with valid signature → `200` and headers present.

3. **PUT with HMAC**: `PUT /api/woocommerce/order` with valid signature → ensure HMAC path covers non-GET bodies.

4. **Proxy IP Resolution**: From behind your real proxy/CDN → confirm `ip()` resolves to client; if not, fix trusted proxies.

### Debug Mode (Development/Staging Only)

In non-production environments (`local` or `staging`), 401 responses include a `reason` field to help with debugging:
- `timestamp_skew` - Timestamp outside the 5-minute window
- `nonce_replay` - Nonce has been used before (replay attack)
- `bad_mac` - Signature doesn't match expected value

Example response:
```json
{
  "success": false,
  "message": "Invalid signature",
  "reason": "timestamp_skew"
}
```

**Note**: This field is **never** included in production responses for security reasons.

