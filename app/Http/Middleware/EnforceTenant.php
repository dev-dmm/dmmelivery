<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Middleware\Concerns\IdentifiesTenant;

/**
 * EnforceTenant Middleware
 * 
 * Purpose: Strict tenant enforcement for API routes.
 * 
 * Behavior:
 * - Requires authentication (returns 401 for unauthenticated requests)
 * - Always enforces tenant presence (no exceptions)
 * - Returns JSON error responses (not redirects) for API compatibility
 * - Uses unified tenant resolver (admin override, route param, subdomain, user)
 * - Binds tenant to container and request attributes
 * - Does not mutate session/auth state (token-based auth compatible)
 * 
 * Use this middleware for API routes that require strict tenant isolation.
 * 
 * Note: This middleware is stricter than IdentifyTenant and does not
 * allow guest access or route-specific exceptions. Renamed from TenantScope
 * to avoid confusion with Eloquent global scopes.
 */
class EnforceTenant
{
    use IdentifiesTenant;

    /**
     * Handle an incoming request.
     * 
     * Enforce tenant presence for API routes with strict validation.
     * Uses the same guard as the route (sanctum) to avoid edge cases.
     * 
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Require authentication using sanctum guard (matches route middleware)
        // This ensures guard parity and avoids edge cases where default guard differs
        if (!$request->user('sanctum')) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Authentication required to access this resource.'
            ], 401);
        }

        // Resolve tenant using unified resolver
        $tenant = $this->resolveTenantFromRequest($request);

        // Always enforce tenant for API routes
        if (!$this->isValidTenant($tenant)) {
            // No logout for token APIs - just return error
            $this->clearTenant();
            return response()->json($this->invalidTenantPayload(), 403);
        }

        // Bind tenant to container and request
        $this->bindTenant($request, $tenant);

        return $next($request);
    }
}

