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

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? new UserResource($user) : null,
                'abilities' => $user ? [
                    'manageUsers'      => Gate::allows('manage-users'),
                    'manageTenants'    => Gate::allows('manage-tenants'),
                    'viewReports'      => Gate::allows('view-reports'),
                    'accessSuperAdmin' => Gate::allows('access-super-admin'),
                    'manageSettings'   => Gate::allows('manage-settings'),
                ] : [],
            ],

            // ✅ Share tenant - try container first, then user relationship
            'tenant' => function () use ($user) {
                // First try the bound tenant from container
                if (app()->bound('tenant')) {
                    return new TenantResource(app('tenant'));
                }
                
                // Fallback to user's tenant if user is authenticated
                if ($user && $user->tenant) {
                    return new TenantResource($user->tenant);
                }
                
                return null;
            },

            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error'   => fn () => $request->session()->get('error'),
            ],

            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
        ]);
    }
}
