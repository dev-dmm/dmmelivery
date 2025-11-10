<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class VerifyWooHmac
{
    /**
     * Handle an incoming request.
     * 
     * Validates:
     * - Content-Type is application/json (for non-GET/HEAD methods)
     * - Payload size is reasonable (max 2MB)
     * - API key authentication (global bridge key or tenant token)
     * - HMAC signature verification (if required or provided)
     * - Replay protection (timestamp and nonce)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Enforce Content-Type only for methods that carry a body
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            $contentType = (string) $request->header('Content-Type', '');
            if (!str_starts_with($contentType, 'application/json')) {
                Log::warning('WooCommerce request rejected: Invalid Content-Type', [
                    'content_type' => $contentType,
                    'method' => $method
                ]);
                return response()->json(['success' => false, 'message' => 'Content-Type must be application/json'], 415);
            }
        }

        // Enforce payload size limit (2MB) - check actual payload size
        $payload = $request->getContent();
        if (strlen($payload) > 2 * 1024 * 1024) { // 2MB
            Log::warning('WooCommerce request rejected: Payload too large', [
                'payload_length' => strlen($payload)
            ]);
            return response()->json(['success' => false, 'message' => 'Payload too large'], 413);
        }

        // Read headers
        $headerKey = (string) $request->header('X-Api-Key', '');
        $tenantId  = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if ($headerKey === '' || $tenantId === '') {
            Log::warning('WooCommerce request rejected: Missing API key or tenant ID');
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Find tenant with caching (5-minute TTL for performance)
        // Use tagged cache if available for surgical invalidation
        $cacheStore = \Cache::getStore();
        if (method_exists($cacheStore, 'tags')) {
            $tenant = \Cache::tags(['tenants'])->remember("tenant:{$tenantId}", 300, function () use ($tenantId) {
                return Tenant::find($tenantId);
            });
        } else {
            $tenant = \Cache::remember("tenant:{$tenantId}", 300, function () use ($tenantId) {
                return Tenant::find($tenantId);
            });
        }
        
        if (!$tenant) {
            Log::warning('WooCommerce request rejected: Invalid tenant ID', ['tenant_id' => $tenantId]);
            return response()->json(['success' => false, 'message' => 'Invalid tenant'], 422);
        }

        // Validate API key (global bridge key OR tenant-specific token)
        $globalKey = (string) config('services.dm_bridge.key');
        $isGlobalKeyValid = $globalKey && hash_equals($globalKey, $headerKey);
        $isTenantTokenValid = $tenant->isApiTokenValid($headerKey);

        if (!$isGlobalKeyValid && !$isTenantTokenValid) {
            Log::warning('WooCommerce request rejected: Invalid API key', [
                'tenant_id' => $tenantId,
                'api_key_provided' => !empty($headerKey),
                'global_key_valid' => $isGlobalKeyValid,
                'tenant_token_valid' => $isTenantTokenValid
            ]);
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Check if tenant requires signed webhooks
        $signatureHeader = (string) $request->header('X-Payload-Signature', '');
        if ($tenant->require_signed_webhooks && $signatureHeader === '') {
            Log::warning('WooCommerce request rejected: Missing signature on tenant that requires it', [
                'tenant_id' => $tenant->id
            ]);
            return response()->json(['success' => false, 'message' => 'Signature required'], 401);
        }

        // Verify signature if provided
        if ($signatureHeader !== '') {
            $verifier = app(\App\Support\HmacVerifier::class);
            if (!$verifier->verify($request, $tenant, $isGlobalKeyValid)) {
                $response = ['success' => false, 'message' => 'Invalid signature'];
                
                // Include debug reason in non-production environments
                if (app()->isLocal() || app()->environment('staging')) {
                    $reason = $verifier->getLastReason();
                    if ($reason) {
                        $response['reason'] = $reason;
                    }
                }
                
                Log::warning('WooCommerce request rejected: Invalid payload signature', [
                    'tenant_id' => $tenant->id,
                    'has_signature_header' => !empty($signatureHeader),
                    'reason' => $verifier->getLastReason()
                ]);
                return response()->json($response, 401);
            }
            // Mark HMAC as verified after successful verification
            $verifier->clearLastReason();
            $request->attributes->set('hmac_verified', true);
        }

        // Emit hmac_verified even when missing (explicit false for stable shape)
        if (!$request->attributes->has('hmac_verified')) {
            $request->attributes->set('hmac_verified', false);
        }

        // Attach tenant and auth info to request for downstream use
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('used_global_key', $isGlobalKeyValid);

        return $next($request);
    }
}
