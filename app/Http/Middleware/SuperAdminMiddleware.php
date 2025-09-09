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
        
        // Check if user has super admin role
        if ($user->isSuperAdmin()) {
            return $next($request);
        }
        
        // Fallback: Check by specific emails for backward compatibility
        $superAdminEmails = [
            'admin@dmm.gr',
            'dev@dmm.gr',
            'super@dmm.gr'
        ];
        
        if (in_array($user->email, $superAdminEmails)) {
            return $next($request);
        }
        
        abort(403, 'Access denied. Super admin privileges required.');
    }
}
