<?php

namespace App\Support;

use Illuminate\Http\Request;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class HmacVerifier
{
    /**
     * Last verification failure reason (for debugging)
     * 
     * @var string|null
     */
    private ?string $lastReason = null;

    /**
     * Verify HMAC payload signature with replay protection
     *
     * @param Request $request
     * @param Tenant $tenant
     * @param bool $isGlobalKey Whether the request used global bridge key
     * @return bool
     */
    public function verify(Request $request, Tenant $tenant, bool $isGlobalKey): bool
    {
        $provided = (string) $request->header('X-Payload-Signature');
        
        // If signature header is not provided, skip verification (backward compatibility)
        if ($provided === '') {
            return true;
        }

        // Replay protection: Check timestamp and nonce
        $timestamp = (int) $request->header('X-Timestamp', 0);
        $nonce = (string) $request->header('X-Nonce', '');
        
        // Verify timestamp is within acceptable window (5 minutes)
        if ($timestamp > 0) {
            $timeDiff = abs(now()->timestamp - $timestamp);
            if ($timeDiff > 300) { // 5 minutes
                $this->lastReason = 'timestamp_skew';
                Log::warning('Payload signature rejected: Timestamp out of window', [
                    'tenant_id' => $tenant->id,
                    'time_diff' => $timeDiff,
                    'request_timestamp' => $timestamp,
                    'server_timestamp' => now()->timestamp
                ]);
                return false;
            }
        }
        
        // Check for replay attacks using nonce
        if ($nonce !== '') {
            $replayKey = "hmac:replay:{$tenant->id}:{$nonce}";
            // Try to add to cache - if it already exists, this is a replay
            if (!\Cache::add($replayKey, 1, 600)) { // 10 minute TTL
                $this->lastReason = 'nonce_replay';
                Log::warning('Payload signature rejected: Replay attack detected', [
                    'tenant_id' => $tenant->id,
                    'nonce' => substr($nonce, 0, 8) . '...' // Log partial nonce only
                ]);
                return false;
            }
        }

        // Get the raw JSON payload
        $payload = $request->getContent();
        
        // If content is empty, reconstruct from request data
        if ($payload === '') {
            $payload = json_encode($request->all(), JSON_UNESCAPED_SLASHES);
            // Use debug level - empty body is common for GET/HEAD where we sign "ts.nonce."
            Log::debug('Using reconstructed payload for signature verification', [
                'tenant_id' => $tenant->id
            ]);
        }

        // If timestamp/nonce are provided, include them in the signed payload
        // Format: "timestamp.nonce.payload" or "timestamp.payload" or "nonce.payload" or just "payload"
        // Accept either one or both - include whichever is present
        $signedPayload = $payload;
        $pieces = [];
        if ($timestamp > 0) {
            $pieces[] = (string) $timestamp;
        }
        if ($nonce !== '') {
            $pieces[] = (string) $nonce;
        }
        if (!empty($pieces)) {
            $signedPayload = implode('.', $pieces) . '.' . $payload;
        }

        // Get the secret to use for verification
        $secret = $isGlobalKey
            ? (string) config('services.dm_bridge.secret')
            : ($tenant->getApiSecret() ?? '');

        // If no secret is configured, signature verification is skipped
        // This allows backward compatibility with existing integrations
        // Note: For production, ensure either global bridge secret or tenant secrets are configured
        if ($secret === '') {
            Log::info('Payload signature verification skipped: No secret configured', [
                'tenant_id' => $tenant->id,
                'is_global_key' => $isGlobalKey,
                'has_global_secret' => !empty(config('services.dm_bridge.secret')),
                'has_tenant_secret' => !empty($tenant->getApiSecret())
            ]);
            return true;
        }

        // Compute expected signatures in multiple formats
        $expectedHex = hash_hmac('sha256', $signedPayload, $secret);
        $expectedB64 = base64_encode(hash_hmac('sha256', $signedPayload, $secret, true));
        $normalized = strtolower($provided);

        // Accept common signature formats (hex, base64, with/without prefix)
        $candidates = [
            $expectedHex,
            "sha256={$expectedHex}",
            strtolower("sha256={$expectedHex}"),
            $expectedB64,
            "sha256={$expectedB64}",
            strtolower("sha256={$expectedB64}"),
        ];

        foreach ($candidates as $candidate) {
            if (hash_equals($candidate, $provided) || hash_equals(strtolower($candidate), $normalized)) {
                return true;
            }
        }

        $this->lastReason = 'bad_mac';
        Log::warning('Payload signature verification failed', [
            'tenant_id' => $tenant->id,
            'is_global_key' => $isGlobalKey,
            'has_secret' => !empty($secret),
            'payload_length' => strlen($signedPayload),
            'provided_format' => substr($provided, 0, 20) . '...' // Log partial signature only
        ]);

        return false;
    }

    /**
     * Get the last verification failure reason (for debugging)
     * 
     * @return string|null One of: 'timestamp_skew', 'nonce_replay', 'bad_mac', or null if verification succeeded
     */
    public function getLastReason(): ?string
    {
        return $this->lastReason;
    }

    /**
     * Clear the last reason (call after successful verification)
     */
    public function clearLastReason(): void
    {
        $this->lastReason = null;
    }
}
