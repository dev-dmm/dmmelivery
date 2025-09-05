<?php
// app/Http/Middleware/TenantScope.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant;

class TenantScope
{

    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $tenant = $user->tenant;

        if (!$tenant || !$tenant->is_active) {
            Auth::logout();
            return redirect()->route('login')
                ->with('error', 'Your account is inactive or tenant not found.');
        }

        // Set tenant context globally
        app()->instance('tenant', $tenant);
        
        return $next($request);
    }
}