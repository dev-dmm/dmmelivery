<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\UserResource;
use App\Http\Resources\TenantResource;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $isSuperAdminRoute = $request->routeIs('super-admin.*');
                
        return array_merge(parent::share($request), [
            'auth' => [
                // Lean user data using Resource
                'user' => $user ? new UserResource($user) : null,
                
                // Boolean abilities using Gates
                'abilities' => $user ? [
                    'manageUsers' => Gate::allows('manage-users'),
                    'manageTenants' => Gate::allows('manage-tenants'),
                    'viewReports' => Gate::allows('view-reports'),
                    'accessSuperAdmin' => Gate::allows('access-super-admin'),
                    'manageSettings' => Gate::allows('manage-settings'),
                ] : [],
                
                // Minimal tenant context - only when needed
                'tenant' => ($user && !$isSuperAdminRoute && $user->tenant) 
                    ? new TenantResource($user->tenant) 
                    : null,
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // Ziggy configuration (filtered by config/ziggy.php and blade template)
            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
        ]);
    }
}