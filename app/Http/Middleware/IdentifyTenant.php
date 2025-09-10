<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            // ⛔ Super admin area should NOT bind a tenant
            if ($user->isSuperAdmin() && $request->routeIs('super-admin.*')) {
                if (app()->has('tenant')) {
                    app()->forgetInstance('tenant');
                }
                return $next($request);
            }

            // normal (non-superadmin) tenant binding continues below…
            $tenant = $user->tenant;
            if (!$tenant || !$tenant->is_active) {
                auth()->logout();
                return redirect()->route('login')
                    ->with('error', 'Your account is inactive or tenant not found.');
            }

            app()->instance('tenant', $tenant);
            $request->attributes->set('tenant', $tenant);
        }

        return $next($request);
    }

}
