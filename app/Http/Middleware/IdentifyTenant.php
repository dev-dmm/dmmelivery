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
        // Check if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();
            
            // Get the user's tenant
            if ($user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
                
                if ($tenant && $tenant->is_active) {
                    // Bind the tenant to the service container
                    app()->instance('tenant', $tenant);
                    
                    // Store tenant in request for easy access
                    $request->attributes->set('tenant', $tenant);
                } else {
                    // Tenant is inactive or doesn't exist, logout and redirect
                    Auth::logout();
                    return redirect()->route('login')->with('error', 'Your tenant account is inactive or doesn\'t exist.');
                }
            } else {
                // User doesn't have a tenant - create a default one or handle gracefully
                $defaultTenant = Tenant::firstOrCreate([
                    'subdomain' => 'default'
                ], [
                    'name' => 'Default Company',
                    'is_active' => true,
                    'onboarding_status' => 'active',
                    'onboarding_started_at' => now(),
                    'onboarding_completed_at' => now(),
                    'subscription_plan' => 'free',
                    'monthly_shipment_limit' => 100,
                    'current_month_shipments' => 0,
                    'billing_cycle_start' => now()->startOfMonth(),
                    'contact_name' => 'Admin User',
                    'contact_email' => $user->email ?? 'admin@default.com',
                    'business_address' => 'N/A',
                    'city' => 'Athens',
                    'postal_code' => '12345',
                    'country' => 'GR',
                    'enabled_features' => ['basic_tracking', 'email_notifications'],
                    'theme_config' => [
                        'primary_color' => '#3B82F6',
                        'secondary_color' => '#6B7280',
                        'logo_position' => 'left',
                        'font_family' => 'Inter',
                    ],
                ]);
                
                // Assign the default tenant to the user
                $user->tenant_id = $defaultTenant->id;
                $user->save();
                
                // Bind the tenant to the service container
                app()->instance('tenant', $defaultTenant);
                
                // Store tenant in request for easy access
                $request->attributes->set('tenant', $defaultTenant);
            }
        }
        
        return $next($request);
    }
}
