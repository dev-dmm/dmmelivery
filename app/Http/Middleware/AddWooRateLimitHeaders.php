<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AddWooRateLimitHeaders
{
    /**
     * Handle an incoming request.
     * 
     * Adds standard rate-limit headers to WooCommerce API responses
     * so integrators can see rate-limit information in any response.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $ip     = $request->ip() ?? 'noip';
        $tenant = $request->header('X-Tenant-Id') ?? $request->input('tenant_id') ?? 'notenant';
        
        // Normalize tenant ID to match rate limiter key (avoid bucket fragmentation)
        $tenant = strtolower(trim($tenant));
        
        $bucket = "woo:{$tenant}:{$ip}";
        $limit  = (int) config('rate.woocommerce_per_minute', 60);

        $remaining  = max(0, RateLimiter::remaining($bucket, $limit));
        $retryAfter = RateLimiter::availableIn($bucket);

        // Standard-ish headers
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        
        if ($retryAfter > 0) {
            $response->headers->set('Retry-After', (string) $retryAfter);
            $response->headers->set('X-RateLimit-Reset', (string) now()->addSeconds($retryAfter)->timestamp);
        }

        return $response;
    }
}

