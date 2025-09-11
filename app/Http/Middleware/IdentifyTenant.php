<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Bind the current user's tenant into the container and request.
     * Enforce tenant presence for all web pages except super-admin area.
     */

    public function handle(Request $request, Closure $next): Response
    {
        // Guest: do nothing (very important so /login works)
        if (!auth()->check()) {
            if (app()->has('tenant')) app()->forgetInstance('tenant');
            return $next($request);
        }

        $user = auth()->user();

        // Super-admin area should not bind a tenant
        if ($user->isSuperAdmin() && $request->routeIs('super-admin.*')) {
            if (app()->has('tenant')) app()->forgetInstance('tenant');
            return $next($request);
        }

        // Profile routes: don't enforce, but bind if present
        $isProfile = $request->routeIs('profile.*');
        $tenant = $user->tenant ?? null;

        if (!$isProfile) {
            // ENFORCE tenant for normal routes
            if (!$tenant || !$tenant->is_active) {
                // 🔴 THIS prevents the login redirect loop
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if (app()->has('tenant')) app()->forgetInstance('tenant');

                return redirect()->route('login')
                    ->with('error', 'Your account is inactive or tenant not found.');
            }
        }

        // Bind when available (also for profile.* so header works)
        if ($tenant) {
            app()->instance('tenant', $tenant);
            $request->attributes->set('tenant', $tenant);
        } else {
            if (app()->has('tenant')) app()->forgetInstance('tenant');
        }

        return $next($request);
    }

}
