<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        
        // Check if user is super admin (you can adjust this logic based on your needs)
        // Option 1: Check by email domain or specific emails
        $superAdminEmails = [
            'admin@dmm.gr',
            'dev@dmm.gr',
            'super@dmm.gr'
        ];
        
        if (in_array($user->email, $superAdminEmails)) {
            return $next($request);
        }
        
        // Option 2: Check by a role/permission system if you have one
        // if ($user->hasRole('super_admin') || $user->can('view_all_tenants')) {
        //     return $next($request);
        // }
        
        // Option 3: Check by a specific user field (if you add is_super_admin column)
        // if ($user->is_super_admin) {
        //     return $next($request);
        // }
        
        abort(403, 'Access denied. Super admin privileges required.');
    }
}
