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
        // NOTE: use closures so props are only computed if accessed on the client
        return array_merge(parent::share($request), [
            // Flash messages (harmless + useful)
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'message' => $request->session()->get('message'),
            ],

            // Auth: share a VERY small shape. UserResource is fine if it's minimal (see below).
            // If you don't need auth data on most pages, you can even switch this to null in production.
            'auth' => fn () => [
                'user' => $request->user() ? new UserResource($request->user()) : null,
                // Only expose ability booleans that the FRONTEND actually checks.
                // If the UI doesn't read them, remove this block entirely.
                'abilities' => $request->user() ? [
                    'viewReports' => Gate::allows('view-reports'),
                ] : [],
            ],

            // Tenant: keep it tiny (id + display name). Use Resource that hides internals.
            'tenant' => function () use ($request) {
                $tenant = app()->bound('tenant') ? app('tenant') : optional($request->user())->tenant;
                return $tenant ? new TenantResource($tenant) : null;
            },

            // Ziggy: avoid dumping ALL routes. Only include a small allowlist you actually call from the client.
            // If you need more routes later, add them here, or configure ziggy.php.
            'ziggy' => function () use ($request) {
                $ziggy = new Ziggy;
                $array = $ziggy->toArray();

                // Allowlist of route names used in the SPA (adjust to your app)
                $allow = [
                    'dashboard',
                    'shipments.index',
                    'shipments.show',
                    'orders.index',
                    'orders.show',
                    'settings.*', // wildcard if you actually use it
                    'super-admin.*', // super admin routes
                    'profile.*', // profile routes
                    'courier.performance',
                    'courier-reports.*',
                    'test.*',
                ];

                // Filter routes by name
                $array['routes'] = collect($array['routes'])
                    ->filter(function ($route, $name) use ($allow) {
                        foreach ($allow as $pattern) {
                            if ($pattern === $name) return true;
                            if (str_ends_with($pattern, '.*') && str_starts_with($name, rtrim($pattern, '.*'))) {
                                return true;
                            }
                        }
                        return false;
                    })
                    ->all();

                // Keep current location (useful for Ziggy)
                $array['location'] = $request->url();

                return $array;
            },
        ]);
    }
}
