<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Middleware\Concerns\IdentifiesTenant;

/**
 * IdentifyTenant Middleware
 * 
 * Purpose: Flexible tenant identification for web routes.
 * 
 * Behavior:
 * - Supports flag-based configuration via route middleware parameters
 * - Allows guest users through when 'allow-guest' flag is set
 * - Does not enforce tenant when 'allow-tenantless' flag is set
 * - Allows super-admin bypass when 'allow-superadmin' flag is set
 * - Uses unified tenant resolver (admin override, route param, subdomain, user)
 * - Binds tenant to container and request attributes when available
 * 
 * Usage:
 * Route::middleware(['identify.tenant'])->group(...) // Default: enforce tenant
 * Route::middleware(['identify.tenant:allow-guest'])->group(...) // Allow guests
 * Route::middleware(['identify.tenant:allow-guest,allow-tenantless'])->group(...) // Flexible
 * 
 * Use this middleware for web routes that need flexible tenant handling.
 */
class IdentifyTenant
{
    use IdentifiesTenant;

    /**
     * Handle an incoming request.
     * 
     * Bind the current user's tenant into the container and request.
     * Enforce tenant presence based on route flags and context.
     * 
     * @param Request $request
     * @param Closure $next
     * @param string ...$flags Optional flags: 'allow-guest', 'allow-tenantless', 'allow-superadmin'
     * @return Response
     */
    public function handle(Request $request, Closure $next, ...$flags): Response
    {
        $allowGuest = in_array('allow-guest', $flags, true);
        $allowTenantless = in_array('allow-tenantless', $flags, true);
        $allowSuperAdmin = in_array('allow-superadmin', $flags, true);

        // Guest handling
        if (!auth()->check()) {
            if ($allowGuest) {
                $this->clearTenant();
                return $next($request);
            }
            // Default: redirect guests to login
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Resolve tenant using unified resolver
        $tenant = $this->resolveTenantFromRequest($request);

        // Super-admin handling
        if ($allowSuperAdmin && $user->isSuperAdmin()) {
            // Bind if present, but don't enforce
            $this->bindTenant($request, $tenant);
            return $next($request);
        }

        // Auto-allow super-admin for super-admin routes
        if ($user->isSuperAdmin() && $request->routeIs('super-admin.*')) {
            $this->clearTenant();
            return $next($request);
        }

        // Auto-allow tenantless for profile routes
        $isProfile = $request->routeIs('profile.*');

        // Validate tenant
        if (!$this->isValidTenant($tenant)) {
            if ($allowTenantless || $isProfile) {
                // Allow through without tenant
                $this->clearTenant();
                return $next($request);
            }

            // Web-specific invalid handling: redirect with message, logout
            if (auth()->check()) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            $this->clearTenant();

            $payload = $this->invalidTenantPayload();
            return redirect()->route('login')->with('error', $payload['message']);
        }

        // Bind tenant and continue
        $this->bindTenant($request, $tenant);
        return $next($request);
    }
}
