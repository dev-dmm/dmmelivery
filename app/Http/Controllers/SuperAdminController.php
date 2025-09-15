<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\Scopes\TenantScope;

class SuperAdminController extends Controller
{
    /**
     * Display all orders across all tenants
     */
    public function orders(Request $request)
    {
        $perPage       = $request->get('per_page', 25);
        $search        = $request->get('search');
        $tenant_filter = $request->get('tenant');
        $status_filter = $request->get('status');

        $query = Order::query()
            // IMPORTANT: bypass tenant scope for super-admin views
            ->withoutGlobalScopes([TenantScope::class])
            ->with([
                'tenant:id,name,subdomain',
                'customer:id,name,email',
                // match FE: load plural
                'shipments:id,order_id,tracking_number,status,courier_id',
                'shipments.courier:id,name,code',
            ])
            ->select(['orders.*']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('external_order_id', 'like', "%{$search}%")
                  ->orWhere('order_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('tenant', function ($tq) use ($search) {
                      $tq->where('name', 'like', "%{$search}%")
                         ->orWhere('subdomain', 'like', "%{$search}%");
                  });
            });
        }

        if ($tenant_filter) {
            $query->where('tenant_id', $tenant_filter);
        }

        if ($status_filter) {
            $query->where('status', $status_filter);
        }

        $orders = $query->orderBy('created_at', 'desc')
                        ->paginate($perPage)
                        ->withQueryString();

        // Tenants for filters — no scope so you see *all* tenants
        $tenants = Tenant::query()
            ->withoutGlobalScopes([TenantScope::class])
            ->select('id', 'name', 'subdomain')
            ->orderBy('name')
            ->get();

        // Distinct order statuses across all tenants
        $orderStatuses = Order::query()
            ->withoutGlobalScopes([TenantScope::class])
            ->select('status')
            ->distinct()
            ->whereNotNull('status')
            ->orderBy('status')
            ->pluck('status');

        $stats = $this->getOrderStats();

        return Inertia::render('SuperAdmin/Orders', [
            'orders'        => $orders,
            'tenants'       => $tenants,
            'orderStatuses' => $orderStatuses,
            'filters'       => [
                'search'   => $search,
                'tenant'   => $tenant_filter,
                'status'   => $status_filter,
                'per_page' => $perPage,
            ],
            'stats'         => $stats,
        ]);
    }

    /**
     * Dashboard stats across all tenants
     */
    private function getOrderStats()
    {
        return [
            'total_orders'       => Order::withoutGlobalScopes([TenantScope::class])->count(),
            'total_tenants'      => Tenant::withoutGlobalScopes([TenantScope::class])->count(),
            'total_shipments'    => Shipment::withoutGlobalScopes([TenantScope::class])->count(),
            'orders_today'       => Order::withoutGlobalScopes([TenantScope::class])->whereDate('created_at', today())->count(),
            'orders_this_week'   => Order::withoutGlobalScopes([TenantScope::class])->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'orders_this_month'  => Order::withoutGlobalScopes([TenantScope::class])->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'pending_shipments'  => Shipment::withoutGlobalScopes([TenantScope::class])->where('status', 'pending')->count(),
            'active_tenants'     => Tenant::withoutGlobalScopes([TenantScope::class])
                ->whereHas('orders', function ($q) {
                    $q->withoutGlobalScopes([TenantScope::class])
                      ->where('created_at', '>=', now()->subDays(30));
                })->count(),
        ];
    }

    /**
     * Super admin dashboard
     */
    public function dashboard()
    {
        $stats = $this->getOrderStats();
    
        $recentOrdersQ = Order::withoutGlobalScopes([TenantScope::class])
            ->with([
                'tenant:id,name,subdomain,business_name',
                'customer:id,name,email',
                'shipments' => fn ($q) => $q->withoutGlobalScopes([TenantScope::class]),
                'shipments.courier:id,name,code',
            ])
            ->orderBy('created_at', 'desc')
            ->limit(10);
    
        $topTenantsQ = Tenant::withoutGlobalScopes([TenantScope::class])
            ->withCount(['orders' => fn ($q) => $q->withoutGlobalScopes([TenantScope::class])
                ->where('created_at', '>=', now()->subDays(30))])
            ->having('orders_count', '>', 0)
            ->orderBy('orders_count', 'desc')
            ->limit(10)
            ->select(['id','name','subdomain']);
    
        return Inertia::render('SuperAdmin/Dashboard', [
            'stats'        => $stats,                               // eager
            'recentOrders' => Inertia::lazy(fn() => $recentOrdersQ->get()),
            'topTenants'   => Inertia::lazy(fn() => $topTenantsQ->get()),
        ]);
    }
    /**
     * Tenant details
     */
    public function tenantDetails(Tenant $tenant)
    {
        $tenant->load([
            'users:id,tenant_id,first_name,last_name,email,email_verified_at,created_at',
            'orders' => function ($q) {
                $q->withoutGlobalScopes([TenantScope::class])
                  ->with(['customer:id,name,email'])
                  ->orderBy('created_at', 'desc')
                  ->limit(20);
            },
            'couriers:id,tenant_id,name,code,is_active,is_default',
        ]);

        $tenantStats = [
            'total_orders'       => $tenant->orders()->withoutGlobalScopes([TenantScope::class])->count(),
            'total_shipments'    => Shipment::withoutGlobalScopes([TenantScope::class])->where('tenant_id', $tenant->id)->count(),
            'orders_this_month'  => $tenant->orders()->withoutGlobalScopes([TenantScope::class])->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'pending_shipments'  => Shipment::withoutGlobalScopes([TenantScope::class])->where('tenant_id', $tenant->id)->where('status', 'pending')->count(),
        ];

        return Inertia::render('SuperAdmin/TenantDetails', [
            'tenant' => $tenant,
            'stats'  => $tenantStats,
        ]);
    }

    /**
     * List all tenants
     */
    public function tenants(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search  = $request->get('search');

        $query = Tenant::withoutGlobalScopes([TenantScope::class])
            ->withCount([
                'orders' => fn ($q) => $q->withoutGlobalScopes([TenantScope::class]),
                'users',
            ])
            ->with(['users' => function ($q) {
                $q->select('id', 'tenant_id', 'first_name', 'last_name', 'email', 'created_at')
                  ->orderBy('created_at');
            }]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        $tenants = $query->orderBy('created_at', 'desc')
                         ->paginate($perPage)
                         ->withQueryString();

        return Inertia::render('SuperAdmin/Tenants', [
            'tenants' => $tenants,
            'filters' => [
                'search'   => $search,
                'per_page' => $perPage,
            ],
        ]);
    }
}
